<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvitoIntegration;
use App\Models\AvitoGeneratedAd;
use App\Services\AvitoApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AvitoAdsController extends Controller
{
    protected $avitoApiService;

    public function __construct(AvitoApiService $avitoApiService)
    {
        $this->avitoApiService = $avitoApiService;
    }

    /**
     * Получить список объявлений
     */
    public function index(Request $request)
    {
        $integration = AvitoIntegration::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'error' => 'Интеграция не настроена или не активна',
            ], 404);
        }

        // Получаем userId из информации об аккаунте
        // Согласно документации Авито, endpoint /accounts/self возвращает информацию о текущем аккаунте
        $testResult = $this->avitoApiService->testConnection($integration);
        if (!$testResult['success']) {
            \Log::error('Avito API: Failed to get user ID in index', [
                'error' => $testResult['error'] ?? 'Unknown error',
                'data' => $testResult['data'] ?? null,
                'details' => $testResult['details'] ?? null,
                'status' => $testResult['details']['error']['code'] ?? null,
            ]);
            return response()->json([
                'success' => false,
                'error' => $testResult['error'] ?? 'Ошибка подключения к API Авито',
                'message' => 'Не удалось получить информацию об аккаунте. Возможно, токен недействителен или недостаточно прав доступа.',
                'details' => $testResult,
            ], 400);
        }
        
        // Проверяем структуру ответа - userId может быть в разных полях
        // Согласно документации Авито, ответ от /accounts/self может содержать разные поля
        $data = $testResult['data'] ?? [];
        $userId = $data['id'] 
               ?? $data['user_id'] 
               ?? $data['account_id']
               ?? $data['userId']
               ?? null;
        
        \Log::info('Avito API: Extracting user ID', [
            'data_structure' => $data,
            'data_keys' => is_array($data) ? array_keys($data) : 'not_array',
            'found_user_id' => $userId,
        ]);
        
        if (!$userId) {
            \Log::error('Avito API: User ID not found in response', [
                'test_result' => $testResult,
                'data' => $data,
                'all_keys' => is_array($data) ? array_keys($data) : 'not_array',
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Не удалось определить ID пользователя из ответа API',
                'details' => [
                    'test_result' => $testResult,
                    'data_structure' => $data,
                    'available_keys' => is_array($data) ? array_keys($data) : 'not_array',
                ],
            ], 400);
        }

        $params = $request->only(['per_page', 'page', 'status', 'category_id']);
        $result = $this->avitoApiService->getAds($integration, $userId, $params);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Получить информацию об объявлении
     */
    public function show(Request $request, $itemId)
    {
        $integration = AvitoIntegration::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'error' => 'Интеграция не настроена или не активна',
            ], 404);
        }

        $userId = $request->input('user_id');
        if (!$userId) {
            // Получаем userId из теста подключения
            $testResult = $this->avitoApiService->testConnection($integration);
            if (!$testResult['success']) {
                return response()->json($testResult, 400);
            }
            $userId = $testResult['data']['id'] ?? null;
        }

        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось определить ID пользователя',
            ], 400);
        }

        $result = $this->avitoApiService->getAd($integration, $itemId, $userId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Создать объявление
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|integer',
            'location_id' => 'required|integer',
            'price' => 'required|numeric|min:0',
            'contact_phone' => 'required|string',
            'images' => 'sometimes|array',
            'images.*' => 'url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $integration = AvitoIntegration::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'error' => 'Интеграция не настроена или не активна',
            ], 404);
        }

        // Получаем userId
        $testResult = $this->avitoApiService->testConnection($integration);
        if (!$testResult['success']) {
            return response()->json($testResult, 400);
        }
        $userId = $testResult['data']['id'] ?? null;

        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось определить ID пользователя',
            ], 400);
        }

        // Формируем данные объявления для сферы услуг
        $adData = [
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'location' => [
                'city_id' => $request->location_id,
            ],
            'price' => (int)($request->price * 100), // Цена в копейках
            'contact' => [
                'phone' => $request->contact_phone,
            ],
            'images' => $request->images ?? [],
            'service_type' => $request->service_type ?? 'service',
        ];

        // Добавляем дополнительные параметры из настроек
        if ($request->has('params')) {
            $adData = array_merge($adData, $request->params);
        }

        $result = $this->avitoApiService->createAd($integration, $userId, $adData);

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Массовое создание объявлений по городам
     * Поддерживает два режима:
     * 1. Использование сгенерированного объявления (generated_ad_id)
     * 2. Использование шаблона (template) - для обратной совместимости
     */
    public function massCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'generated_ad_id' => 'sometimes|integer|exists:avito_generated_ads,id',
            'template' => 'sometimes|array',
            'template.title' => 'required_with:template|string|max:255',
            'template.description' => 'required_with:template|string',
            'template.category_id' => 'required_with:template|integer',
            'template.price' => 'required_with:template|numeric|min:0',
            'template.contact_phone' => 'required_with:template|string',
            'template.images' => 'sometimes|array',
            'cities' => 'required|array|min:1',
            'cities.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $integration = AvitoIntegration::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'error' => 'Интеграция не настроена или не активна',
            ], 404);
        }

        // Получаем userId
        $testResult = $this->avitoApiService->testConnection($integration);
        if (!$testResult['success']) {
            return response()->json($testResult, 400);
        }
        $userId = $testResult['data']['id'] ?? null;

        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось определить ID пользователя',
            ], 400);
        }

        // Определяем источник данных объявления
        $template = null;
        
        if ($request->has('generated_ad_id')) {
            // Используем сгенерированное объявление
            $generatedAd = \App\Models\AvitoGeneratedAd::where('user_id', $request->user()->id)
                ->findOrFail($request->generated_ad_id);
            
            // Преобразуем локальные пути изображений в полные URL
            $images = [];
            if ($generatedAd->images && is_array($generatedAd->images)) {
                foreach ($generatedAd->images as $image) {
                    if (is_string($image)) {
                        // Если путь локальный, преобразуем в полный URL
                        if (str_starts_with($image, '/storage/')) {
                            $images[] = url($image);
                        } elseif (!str_starts_with($image, 'http')) {
                            $images[] = url('/storage/' . $image);
                        } else {
                            $images[] = $image;
                        }
                    }
                }
            }
            
            $template = [
                'title' => $generatedAd->title,
                'description' => $generatedAd->description,
                'category_id' => $generatedAd->category_id,
                'price' => $generatedAd->price ?? 0,
                'contact_phone' => $generatedAd->contact_phone ?? '',
                'images' => $images,
                'service_type' => 'service',
                'is_vip' => $generatedAd->is_vip ?? false,
                'is_premium' => $generatedAd->is_premium ?? false,
                'is_auto_renew' => $generatedAd->is_auto_renew ?? false,
            ];
        } elseif ($request->has('template')) {
            // Используем шаблон (обратная совместимость)
            $template = $request->template;
        } else {
            return response()->json([
                'success' => false,
                'error' => 'Необходимо указать generated_ad_id или template',
            ], 422);
        }

        $results = [];
        $cities = $request->cities;

        foreach ($cities as $cityId) {
            // Формируем данные объявления для каждого города
            $adData = [
                'title' => $template['title'],
                'description' => $template['description'],
                'category_id' => $template['category_id'],
                'location' => [
                    'city_id' => $cityId,
                ],
                'price' => (int)($template['price'] * 100), // Цена в копейках
                'contact' => [
                    'phone' => $template['contact_phone'],
                ],
                'images' => $template['images'] ?? [],
                'service_type' => $template['service_type'] ?? 'service',
            ];

            // Добавляем дополнительные параметры
            if (isset($template['params'])) {
                $adData = array_merge($adData, $template['params']);
            }

            // Добавляем флаги VIP, Premium, Auto-renew если они есть
            if (isset($template['is_vip']) && $template['is_vip']) {
                $adData['vas'] = $adData['vas'] ?? [];
                $adData['vas'][] = 'vip';
            }
            if (isset($template['is_premium']) && $template['is_premium']) {
                $adData['vas'] = $adData['vas'] ?? [];
                $adData['vas'][] = 'premium';
            }
            if (isset($template['is_auto_renew']) && $template['is_auto_renew']) {
                $adData['auto_renew'] = true;
            }

            $result = $this->avitoApiService->createAd($integration, $userId, $adData);
            
            $results[] = [
                'city_id' => $cityId,
                'success' => $result['success'],
                'data' => $result['data'] ?? null,
                'error' => $result['error'] ?? null,
            ];

            // Небольшая задержка между запросами, чтобы не превысить лимиты API
            if (count($results) < count($cities)) {
                usleep(500000); // 0.5 секунды
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failCount = count($results) - $successCount;

        return response()->json([
            'success' => true,
            'total' => count($results),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'results' => $results,
        ]);
    }

    /**
     * Получить список категорий
     */
    public function getCategories(Request $request)
    {
        $integration = AvitoIntegration::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'error' => 'Интеграция не настроена или не активна',
            ], 404);
        }

        $categoryId = $request->input('category_id');
        $result = $this->avitoApiService->getServiceCategories($integration, $categoryId);

        \Log::info('Avito API: getCategories controller response', [
            'success' => $result['success'],
            'has_data' => isset($result['data']),
            'error' => $result['error'] ?? null,
        ]);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Получить список городов
     */
    public function getLocations(Request $request)
    {
        $integration = AvitoIntegration::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'error' => 'Интеграция не настроена или не активна',
            ], 404);
        }

        $query = $request->input('q');
        $result = $this->avitoApiService->getLocations($integration, $query);

        \Log::info('Avito API: getLocations controller response', [
            'success' => $result['success'],
            'has_data' => isset($result['data']),
            'error' => $result['error'] ?? null,
        ]);

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}

