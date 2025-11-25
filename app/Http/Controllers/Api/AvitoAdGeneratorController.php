<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvitoGeneratedAd;
use App\Services\OpenAIService;
use App\Services\AvitoApiService;
use App\Models\AvitoIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class AvitoAdGeneratorController extends Controller
{
    protected $openAIService;
    protected $avitoApiService;

    public function __construct(OpenAIService $openAIService, AvitoApiService $avitoApiService)
    {
        $this->openAIService = $openAIService;
        $this->avitoApiService = $avitoApiService;
    }

    /**
     * Получить список сгенерированных объявлений
     */
    public function index(Request $request)
    {
        $ads = AvitoGeneratedAd::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $ads,
        ]);
    }

    /**
     * Получить одно объявление
     */
    public function show(Request $request, $id)
    {
        $ad = AvitoGeneratedAd::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ad,
        ]);
    }

    /**
     * Генерация объявлений
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic' => 'required|string|max:500',
            'count' => 'required|integer|min:1|max:10',
            'category_id' => 'required|integer',
            'location_id' => 'required|integer',
            'num_images' => 'integer|min:1|max:10',
            'price' => 'nullable|numeric|min:0',
            'contact_phone' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'condition' => 'nullable|string',
            'is_vip' => 'boolean',
            'is_premium' => 'boolean',
            'is_auto_renew' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $count = $request->input('count', 1);
            $numImages = $request->input('num_images', 1);
            $generatedAds = [];

            // Получаем информацию о категории и локации из Авито API
            $integration = AvitoIntegration::where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->first();

            $categoryName = null;
            $locationName = null;

            if ($integration) {
                // Получаем категории через метод getServiceCategories
                $categoriesResult = $this->avitoApiService->getServiceCategories($integration);
                if ($categoriesResult['success']) {
                    $categoriesData = $categoriesResult['data'];
                    // Структура ответа может быть разной, проверяем разные варианты
                    $categories = $categoriesData['categories'] ?? $categoriesData['data'] ?? [];
                    if (is_array($categories)) {
                        foreach ($categories as $cat) {
                            $catId = $cat['id'] ?? $cat['category_id'] ?? null;
                            if ($catId && $catId == $request->category_id) {
                                $categoryName = $cat['name'] ?? $cat['title'] ?? null;
                                break;
                            }
                        }
                    }
                }

                // Получаем локации
                $locationsResult = $this->avitoApiService->getLocations($integration);
                if ($locationsResult['success']) {
                    $locationsData = $locationsResult['data'];
                    $locations = $locationsData['locations'] ?? $locationsData['data'] ?? [];
                    if (is_array($locations)) {
                        foreach ($locations as $loc) {
                            $locId = $loc['id'] ?? $loc['location_id'] ?? null;
                            if ($locId && $locId == $request->location_id) {
                                $locationName = $loc['name'] ?? $loc['title'] ?? null;
                                break;
                            }
                        }
                    }
                }
            }

            // Генерируем объявления
            for ($i = 0; $i < $count; $i++) {
                Log::info('Generating ad', [
                    'iteration' => $i + 1,
                    'total' => $count,
                    'topic' => $request->topic,
                ]);

                $settings = [
                    'category' => $categoryName,
                    'location' => $locationName,
                    'num_images' => $numImages,
                    'price_range' => $request->price ? "около {$request->price} рублей" : null,
                ];

                // Генерируем текст объявления
                $textResult = $this->openAIService->generateAdText($request->topic, $settings);

                if (!$textResult['success']) {
                    Log::error('Failed to generate ad text', [
                        'error' => $textResult['error'],
                        'iteration' => $i + 1,
                    ]);
                    continue;
                }

                $adData = $textResult['data'];
                $imagePrompts = $adData['image_prompts'] ?? [];

                // Генерируем изображения
                $images = [];
                foreach ($imagePrompts as $prompt) {
                    $imageResult = $this->openAIService->generateImage($prompt);
                    if ($imageResult['success']) {
                        // Сохраняем изображение локально
                        $savedUrl = $this->saveImageFromUrl($imageResult['url'], $request->user()->id);
                        if ($savedUrl) {
                            $images[] = $savedUrl;
                        }
                    }
                    // Небольшая задержка между запросами к OpenAI
                    usleep(500000); // 0.5 секунды
                }

                // Если не удалось сгенерировать изображения через промпты, генерируем по теме
                if (empty($images) && $numImages > 0) {
                    for ($j = 0; $j < $numImages; $j++) {
                        $imagePrompt = "Профессиональное фото для объявления на тему: {$request->topic}. Высокое качество, реалистичное изображение.";
                        $imageResult = $this->openAIService->generateImage($imagePrompt);
                        if ($imageResult['success']) {
                            $savedUrl = $this->saveImageFromUrl($imageResult['url'], $request->user()->id);
                            if ($savedUrl) {
                                $images[] = $savedUrl;
                            }
                        }
                        usleep(500000);
                    }
                }

                // Сохраняем объявление в БД
                $ad = AvitoGeneratedAd::create([
                    'user_id' => $request->user()->id,
                    'title' => $adData['title'] ?? 'Без названия',
                    'description' => $adData['description'] ?? '',
                    'category_id' => $request->category_id,
                    'category_name' => $categoryName,
                    'location_id' => $request->location_id,
                    'location_name' => $locationName,
                    'address' => $request->address,
                    'price' => $request->price,
                    'contact_phone' => $request->contact_phone,
                    'images' => $images,
                    'condition' => $request->condition,
                    'is_vip' => $request->boolean('is_vip', false),
                    'is_premium' => $request->boolean('is_premium', false),
                    'is_auto_renew' => $request->boolean('is_auto_renew', false),
                    'status' => 'ready',
                    'generation_topic' => $request->topic,
                    'generation_prompt' => $textResult['raw_response'] ?? null,
                    'generation_settings' => $settings,
                ]);

                $generatedAds[] = $ad;

                // Небольшая задержка между генерациями
                if ($i < $count - 1) {
                    sleep(1);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Объявления успешно сгенерированы',
                'count' => count($generatedAds),
                'ads' => $generatedAds,
            ]);
        } catch (Exception $e) {
            Log::error('Error generating ads', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка генерации объявлений: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Удалить объявление
     */
    public function destroy(Request $request, $id)
    {
        $ad = AvitoGeneratedAd::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Удаляем изображения
        if ($ad->images) {
            foreach ($ad->images as $imageUrl) {
                $this->deleteImage($imageUrl);
            }
        }

        $ad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Объявление удалено',
        ]);
    }

    /**
     * Сохранить изображение с URL локально
     */
    private function saveImageFromUrl(string $url, int $userId): ?string
    {
        try {
            $imageContent = file_get_contents($url);
            if ($imageContent === false) {
                return null;
            }

            $filename = 'avito-ads/' . $userId . '/' . uniqid() . '.jpg';
            Storage::disk('public')->put($filename, $imageContent);

            return Storage::url($filename);
        } catch (Exception $e) {
            Log::error('Error saving image from URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Удалить изображение
     */
    private function deleteImage(string $url): void
    {
        try {
            $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        } catch (Exception $e) {
            Log::error('Error deleting image', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
