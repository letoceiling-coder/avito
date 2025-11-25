<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OpenAIService
{
    private const BASE_URL = 'https://api.openai.com/v1';
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
        
        if (!$this->apiKey) {
            throw new Exception('OPENAI_API_KEY не настроен в .env файле');
        }
    }

    /**
     * Генерация текста объявления через OpenAI
     */
    public function generateAdText(string $topic, array $settings = []): array
    {
        try {
            $prompt = $this->buildPrompt($topic, $settings);
            
            Log::info('OpenAI: Generating ad text', [
                'topic' => $topic,
                'prompt_length' => strlen($prompt),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post(self::BASE_URL . '/chat/completions', [
                'model' => $settings['model'] ?? 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Ты профессиональный копирайтер, специализирующийся на создании продающих объявлений для Авито. Создавай уникальные, привлекательные и информативные объявления, которые соответствуют требованиям платформы Авито.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => $settings['temperature'] ?? 0.8,
                'max_tokens' => $settings['max_tokens'] ?? 2000,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                
                // Парсим JSON ответ от GPT
                $adData = $this->parseAdResponse($content);
                
                Log::info('OpenAI: Ad text generated successfully', [
                    'has_title' => !empty($adData['title']),
                    'has_description' => !empty($adData['description']),
                ]);

                return [
                    'success' => true,
                    'data' => $adData,
                    'raw_response' => $content,
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Ошибка генерации текста';
            
            // Специальная обработка для деактивированного аккаунта
            if (isset($errorData['error']['code']) && $errorData['error']['code'] === 'account_deactivated') {
                $errorMessage = 'OpenAI API ключ деактивирован. Пожалуйста, проверьте ваш API ключ в настройках OpenAI или создайте новый.';
            }
            
            Log::error('OpenAI: Error generating ad text', [
                'status' => $response->status(),
                'error' => $errorData,
                'error_message' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'error_code' => $errorData['error']['code'] ?? null,
                'details' => $errorData,
            ];
        } catch (Exception $e) {
            Log::error('OpenAI: Exception in generateAdText', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Генерация изображения через DALL-E
     */
    public function generateImage(string $prompt, string $size = '1024x1024'): array
    {
        try {
            Log::info('OpenAI: Generating image', [
                'prompt' => $prompt,
                'size' => $size,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post(self::BASE_URL . '/images/generations', [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'quality' => 'standard',
                'response_format' => 'url',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $imageUrl = $data['data'][0]['url'] ?? null;

                if ($imageUrl) {
                    Log::info('OpenAI: Image generated successfully', [
                        'url' => $imageUrl,
                    ]);

                    return [
                        'success' => true,
                        'url' => $imageUrl,
                    ];
                }
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Ошибка генерации изображения';
            $errorCode = $errorData['error']['code'] ?? null;
            
            // Специальная обработка для лимита биллинга
            if ($errorCode === 'billing_hard_limit_reached') {
                $errorMessage = 'Достигнут лимит биллинга OpenAI. Изображения не будут сгенерированы, но объявление можно сохранить без них.';
            }
            
            Log::warning('OpenAI: Error generating image', [
                'status' => $response->status(),
                'error' => $errorData,
                'error_code' => $errorCode,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'error_code' => $errorCode,
            ];
        } catch (Exception $e) {
            Log::error('OpenAI: Exception in generateImage', [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Построение промпта для генерации объявления
     */
    private function buildPrompt(string $topic, array $settings): string
    {
        $numImages = $settings['num_images'] ?? 1;
        $category = $settings['category'] ?? null;
        $location = $settings['location'] ?? null;
        $priceRange = $settings['price_range'] ?? null;

        $prompt = "Создай уникальное объявление для Авито на тему: {$topic}\n\n";
        
        if ($category) {
            $prompt .= "Категория: {$category}\n";
        }
        
        if ($location) {
            $prompt .= "Город/Регион: {$location}\n";
        }
        
        if ($priceRange) {
            $prompt .= "Ценовой диапазон: {$priceRange}\n";
        }

        $prompt .= "\nТребования к объявлению:\n";
        $prompt .= "1. Заголовок должен быть привлекательным и информативным (до 50 символов)\n";
        $prompt .= "2. Описание должно быть подробным, конструктивным и продающим (300-500 слов)\n";
        $prompt .= "3. Используй эмодзи для визуального выделения\n";
        $prompt .= "4. Укажи ключевые преимущества и особенности\n";
        $prompt .= "5. Добавь призыв к действию\n";
        $prompt .= "6. Сделай текст уникальным и не похожим на другие объявления\n\n";
        
        $prompt .= "Верни ответ в формате JSON со следующей структурой:\n";
        $prompt .= "{\n";
        $prompt .= '  "title": "Заголовок объявления",' . "\n";
        $prompt .= '  "description": "Подробное описание объявления",' . "\n";
        $prompt .= '  "image_prompts": [' . "\n";
        
        for ($i = 1; $i <= $numImages; $i++) {
            $prompt .= "    \"Детальное описание для генерации изображения {$i} на тему объявления\"," . "\n";
        }
        
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";
        $prompt .= "ВАЖНО: Верни ТОЛЬКО валидный JSON, без дополнительного текста до или после JSON.";

        return $prompt;
    }

    /**
     * Парсинг ответа от GPT в структурированные данные
     */
    private function parseAdResponse(string $content): array
    {
        // Убираем markdown код блоки если есть
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        $data = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return [
                'title' => $data['title'] ?? '',
                'description' => $data['description'] ?? '',
                'image_prompts' => $data['image_prompts'] ?? [],
            ];
        }

        // Если не удалось распарсить JSON, пытаемся извлечь данные из текста
        Log::warning('OpenAI: Failed to parse JSON response, trying text extraction', [
            'content_preview' => substr($content, 0, 200),
        ]);

        return [
            'title' => $this->extractTitle($content),
            'description' => $this->extractDescription($content),
            'image_prompts' => [],
        ];
    }

    /**
     * Извлечение заголовка из текста
     */
    private function extractTitle(string $content): string
    {
        // Ищем заголовок в кавычках или после "title:"
        if (preg_match('/"title"\s*:\s*"([^"]+)"/', $content, $matches)) {
            return $matches[1];
        }
        if (preg_match('/title:\s*(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        // Берем первую строку
        $lines = explode("\n", $content);
        return trim($lines[0] ?? '');
    }

    /**
     * Извлечение описания из текста
     */
    private function extractDescription(string $content): string
    {
        // Ищем описание в кавычках или после "description:"
        if (preg_match('/"description"\s*:\s*"([^"]+)"/s', $content, $matches)) {
            return $matches[1];
        }
        if (preg_match('/description:\s*(.+)/is', $content, $matches)) {
            return trim($matches[1]);
        }
        // Берем весь текст кроме первой строки
        $lines = explode("\n", $content);
        array_shift($lines);
        return trim(implode("\n", $lines));
    }
}

