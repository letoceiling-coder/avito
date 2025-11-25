<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvitoIntegration;
use App\Services\AvitoApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AvitoIntegrationController extends Controller
{
    protected $avitoApiService;

    public function __construct(AvitoApiService $avitoApiService)
    {
        $this->avitoApiService = $avitoApiService;
    }

    /**
     * Получить настройки интеграции текущего пользователя
     */
    public function index(Request $request)
    {
        $integration = AvitoIntegration::where('user_id', $request->user()->id)->first();

        if (!$integration) {
            return response()->json([
                'integration' => null,
            ]);
        }

        return response()->json([
            'integration' => [
                'id' => $integration->id,
                'client_id' => $integration->client_id,
                'is_active' => $integration->is_active,
                'is_token_valid' => $integration->isTokenValid(),
                'expires_at' => $integration->expires_at?->toIso8601String(),
                'settings' => $integration->settings,
            ],
        ]);
    }

    /**
     * Сохранить настройки интеграции
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $integration = AvitoIntegration::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
                'is_active' => false,
            ]
        );

        return response()->json([
            'message' => 'Настройки сохранены',
            'integration' => [
                'id' => $integration->id,
                'client_id' => $integration->client_id,
                'is_active' => $integration->is_active,
            ],
        ]);
    }

    /**
     * Обновить настройки интеграции
     */
    public function update(Request $request, $id)
    {
        $integration = AvitoIntegration::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'client_id' => 'sometimes|string',
            'client_secret' => 'sometimes|string',
            'settings' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $integration->update($request->only(['client_id', 'client_secret', 'settings']));

        return response()->json([
            'message' => 'Настройки обновлены',
            'integration' => [
                'id' => $integration->id,
                'client_id' => $integration->client_id,
                'is_active' => $integration->is_active,
            ],
        ]);
    }

    /**
     * Сохранить токены после OAuth авторизации
     */
    public function storeTokens(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'redirect_uri' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $integration = AvitoIntegration::where('user_id', $request->user()->id)->firstOrFail();

        \Log::info('Avito OAuth: Attempting to exchange code for tokens', [
            'integration_id' => $integration->id,
            'code_length' => strlen($request->code),
        ]);

        try {
            $tokens = $this->avitoApiService->getAccessToken(
                $integration->client_id,
                $integration->client_secret,
                $request->code,
                $request->redirect_uri
            );

            \Log::info('Avito OAuth: Tokens received successfully', [
                'integration_id' => $integration->id,
                'has_access_token' => isset($tokens['access_token']),
                'has_refresh_token' => isset($tokens['refresh_token']),
                'expires_in' => $tokens['expires_in'] ?? null,
            ]);

            $integration->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'Токены успешно сохранены',
                'integration' => [
                    'id' => $integration->id,
                    'is_active' => $integration->is_active,
                    'is_token_valid' => $integration->isTokenValid(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Avito OAuth: Error exchanging code for tokens', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Ошибка получения токенов',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Тест подключения к API Авито
     */
    public function testConnection(Request $request)
    {
        $integration = AvitoIntegration::where('user_id', $request->user()->id)->first();

        if (!$integration) {
            return response()->json([
                'success' => false,
                'error' => 'Интеграция не настроена',
            ], 404);
        }

        if (!$integration->is_active || !$integration->access_token) {
            return response()->json([
                'success' => false,
                'error' => 'Токен доступа не установлен. Необходимо авторизоваться через OAuth.',
            ], 400);
        }

        $result = $this->avitoApiService->testConnection($integration);

        return response()->json($result);
    }

    /**
     * Получить URL для OAuth авторизации
     */
    public function getAuthUrl(Request $request)
    {
        $integration = AvitoIntegration::where('user_id', $request->user()->id)->first();

        if (!$integration || !$integration->client_id) {
            return response()->json([
                'error' => 'Интеграция не настроена',
            ], 404);
        }

        $redirectUri = $request->input('redirect_uri', url('/admin/avito/callback'));
        
        // Убеждаемся, что redirect_uri правильно закодирован и не содержит лишних символов
        $redirectUri = rtrim($redirectUri, '/');
        
        // Логируем для отладки
        \Log::info('Avito OAuth URL generation', [
            'client_id' => $integration->client_id,
            'redirect_uri' => $redirectUri,
            'full_url' => url('/admin/avito/callback'),
        ]);
        
        // Согласно документации Авито API, OAuth endpoint:
        // https://www.avito.ru/oauth
        // Параметры должны быть правильно закодированы
        $params = [
            'response_type' => 'code',
            'client_id' => $integration->client_id,
            'redirect_uri' => $redirectUri,
        ];
        
        // Scope согласно документации Авито должен быть через запятую
        // Документация: https://developers.avito.ru/api-catalog/auth/documentation
        // Формат: scope1,scope2,scope3 (через запятую, без пробелов)
        // Пример из документации: messenger:read,messenger:write
        // 
        // Доступные scope для работы с объявлениями (из документации):
        // - items:info - Получение информации об объявлениях
        // - items:apply_vas - Применение дополнительных услуг
        // 
        // Примечание: items:read и items:write могут не существовать
        // Используем items:info согласно официальной документации
        $scope = 'items:info'; // Используем items:info согласно документации
        $params['scope'] = $scope;
        
        $authUrl = 'https://www.avito.ru/oauth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        \Log::info('Avito OAuth URL generated', [
            'auth_url' => $authUrl,
        ]);

        return response()->json([
            'auth_url' => $authUrl,
            'redirect_uri' => $redirectUri, // Возвращаем для отладки
            'client_id' => $integration->client_id, // Для проверки
        ]);
    }
}
