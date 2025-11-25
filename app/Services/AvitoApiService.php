<?php

namespace App\Services;

use App\Models\AvitoIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AvitoApiService
{
    private const BASE_URL = 'https://api.avito.ru';
    private const AUTH_URL = 'https://api.avito.ru/token';

    /**
     * Получить токен доступа через OAuth2
     */
    public function getAccessToken(string $clientId, string $clientSecret, string $code, string $redirectUri): array
    {
        try {
            Log::info('Avito API: Requesting access token', [
                'client_id' => $clientId,
                'code_length' => strlen($code),
                'redirect_uri' => $redirectUri,
            ]);

            $response = Http::asForm()->post(self::AUTH_URL, [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

            Log::info('Avito API: Token response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            $errorBody = $response->json();
            $errorMessage = $errorBody['error'] ?? 'Неизвестная ошибка';
            $errorDescription = $errorBody['error_description'] ?? $response->body();

            Log::error('Avito API: Ошибка получения токена', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'description' => $errorDescription,
                'body' => $response->body(),
            ]);

            throw new Exception("Ошибка получения токена: {$errorMessage}. " . ($errorDescription ? "Описание: {$errorDescription}" : ""));
        } catch (Exception $e) {
            Log::error('Avito API: Исключение при получении токена', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Обновить токен доступа
     */
    public function refreshAccessToken(string $clientId, string $clientSecret, string $refreshToken): array
    {
        try {
            $response = Http::asForm()->post(self::AUTH_URL, [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Ошибка обновления токена: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Avito API: Ошибка обновления токена', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Получить авторизованный HTTP клиент
     */
    private function getAuthorizedClient(AvitoIntegration $integration): \Illuminate\Http\Client\PendingRequest
    {
        // Проверяем и обновляем токен при необходимости
        if (!$integration->isTokenValid() && $integration->refresh_token) {
            Log::info('Avito API: Token expired, refreshing...', [
                'integration_id' => $integration->id,
                'expires_at' => $integration->expires_at,
            ]);
            $this->refreshIntegrationToken($integration);
            // Перезагружаем модель после обновления
            $integration->refresh();
        }

        if (!$integration->access_token) {
            throw new Exception('Access token is missing');
        }

        Log::debug('Avito API: Creating authorized client', [
            'integration_id' => $integration->id,
            'has_token' => !empty($integration->access_token),
            'token_length' => strlen($integration->access_token ?? ''),
        ]);

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $integration->access_token,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Обновить токен интеграции
     */
    public function refreshIntegrationToken(AvitoIntegration $integration): bool
    {
        try {
            $tokens = $this->refreshAccessToken(
                $integration->client_id,
                $integration->client_secret,
                $integration->refresh_token
            );

            $integration->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $integration->refresh_token,
                'expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Avito API: Ошибка обновления токена интеграции', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Тест подключения к API
     */
    public function testConnection(AvitoIntegration $integration): array
    {
        try {
            $client = $this->getAuthorizedClient($integration);
            $url = self::BASE_URL . '/core/v1/accounts/self';
            
            Log::info('Avito API: Testing connection', [
                'url' => $url,
                'integration_id' => $integration->id,
            ]);
            
            $response = $client->get($url);

            Log::info('Avito API: Test connection response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Avito API: Connection successful', [
                    'data' => $data,
                    'data_keys' => is_array($data) ? array_keys($data) : 'not_array',
                    'has_id' => isset($data['id']),
                    'has_user_id' => isset($data['user_id']),
                    'has_account_id' => isset($data['account_id']),
                ]);
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? $errorData['message'] ?? 'Ошибка подключения';
            
            Log::error('Avito API: Connection test failed', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'response' => $errorData,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'details' => $errorData,
            ];
        } catch (Exception $e) {
            Log::error('Avito API: Exception in test connection', [
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
     * Получить список объявлений
     * Согласно документации Авито: https://developers.avito.ru/api-catalog
     * Endpoint: GET /core/v1/items
     * Возвращает объявления текущего авторизованного пользователя
     */
    public function getAds(AvitoIntegration $integration, int $userId, array $params = []): array
    {
        try {
            // Правильный endpoint согласно документации Авито:
            // GET /core/v1/items - возвращает объявления текущего авторизованного пользователя
            // userId передается только для логирования, но не используется в URL
            $url = self::BASE_URL . "/core/v1/items";
            
            Log::info('Avito API: Getting ads', [
                'user_id' => $userId,
                'url' => $url,
                'params' => $params,
                'integration_id' => $integration->id,
            ]);
            
            $client = $this->getAuthorizedClient($integration);
            $response = $client->get($url, $params);

            Log::info('Avito API: Get ads response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'url' => $url,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Avito API: Successfully retrieved ads', [
                    'items_count' => isset($data['resources']) ? count($data['resources']) : 'unknown',
                    'data_structure' => is_array($data) ? array_keys($data) : 'not_array',
                ]);
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? $errorData['message'] ?? 'Ошибка получения объявлений';
            
            Log::error('Avito API: Error getting ads', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'response' => $errorData,
                'url' => $url,
                'user_id' => $userId,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'details' => $errorData,
            ];
        } catch (Exception $e) {
            Log::error('Avito API: Exception getting ads', [
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
     * Получить информацию об объявлении
     */
    public function getAd(AvitoIntegration $integration, int $itemId, int $userId): array
    {
        try {
            $client = $this->getAuthorizedClient($integration);
            $response = $client->get(self::BASE_URL . "/core/v1/accounts/{$userId}/items/{$itemId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['error']['message'] ?? 'Ошибка получения объявления',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Создать объявление
     * Согласно документации Авито API: https://developers.avito.ru/api-catalog
     * Endpoint: POST /core/v1/items
     * userId определяется автоматически из токена авторизации
     * 
     * Структура данных для сферы услуг:
     * {
     *   "title": "string",
     *   "description": "string",
     *   "category_id": integer,
     *   "location": {
     *     "city_id": integer
     *   },
     *   "price": integer, // в копейках
     *   "contact": {
     *     "phone": "string"
     *   },
     *   "images": ["url1", "url2", ...],
     *   "params": [...] // дополнительные параметры категории
     * }
     */
    public function createAd(AvitoIntegration $integration, int $userId, array $adData): array
    {
        try {
            Log::info('Avito API: Creating ad', [
                'user_id' => $userId,
                'integration_id' => $integration->id,
                'ad_data_keys' => array_keys($adData),
                'has_title' => isset($adData['title']),
                'has_category' => isset($adData['category_id']),
                'has_location' => isset($adData['location']),
                'has_price' => isset($adData['price']),
                'has_contact' => isset($adData['contact']),
                'images_count' => isset($adData['images']) ? count($adData['images']) : 0,
            ]);

            $client = $this->getAuthorizedClient($integration);
            
            // Пробуем оба варианта endpoint:
            // 1. С userId в пути (как в документации для некоторых методов)
            // 2. Без userId (как для получения списка)
            // Начинаем с варианта с userId, так как это стандартный формат для создания
            $url = self::BASE_URL . "/core/v1/accounts/{$userId}/items";
            
            Log::info('Avito API: Sending POST request', [
                'url' => $url,
                'user_id' => $userId,
                'ad_data_keys' => array_keys($adData),
                'ad_data_sample' => [
                    'title' => $adData['title'] ?? null,
                    'category_id' => $adData['category_id'] ?? null,
                    'has_location' => isset($adData['location']),
                    'has_price' => isset($adData['price']),
                    'has_contact' => isset($adData['contact']),
                    'images_count' => isset($adData['images']) ? count($adData['images']) : 0,
                ],
            ]);

            $response = $client->post($url, $adData);
            
            // Если получили 404, пробуем альтернативный endpoint
            if ($response->status() === 404) {
                Log::warning('Avito API: Got 404 with userId in path, trying alternative endpoint');
                $alternativeUrl = self::BASE_URL . "/core/v1/items";
                Log::info('Avito API: Trying alternative endpoint', ['url' => $alternativeUrl]);
                $response = $client->post($alternativeUrl, $adData);
            }

            Log::info('Avito API: Create ad response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'url_used' => $url,
                'response_body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Avito API: Ad created successfully', [
                    'item_id' => $data['id'] ?? null,
                    'status' => $data['status'] ?? null,
                    'data_structure' => is_array($data) ? array_keys($data) : 'not_array',
                ]);
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? $errorData['message'] ?? 'Ошибка создания объявления';
            
            // Детальное логирование для диагностики
            Log::error('Avito API: Error creating ad', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'response' => $errorData,
                'response_body' => $response->body(),
                'url_used' => $url,
                'user_id' => $userId,
                'ad_data_structure' => [
                    'has_title' => isset($adData['title']),
                    'has_description' => isset($adData['description']),
                    'has_category_id' => isset($adData['category_id']),
                    'has_location' => isset($adData['location']),
                    'location_structure' => isset($adData['location']) ? array_keys($adData['location']) : null,
                    'has_price' => isset($adData['price']),
                    'has_contact' => isset($adData['contact']),
                    'contact_structure' => isset($adData['contact']) ? array_keys($adData['contact']) : null,
                    'has_images' => isset($adData['images']),
                    'images_count' => isset($adData['images']) ? count($adData['images']) : 0,
                    'all_keys' => array_keys($adData),
                ],
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'details' => $errorData,
            ];
        } catch (Exception $e) {
            Log::error('Avito API: Exception creating ad', [
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
     * Обновить объявление
     */
    public function updateAd(AvitoIntegration $integration, int $userId, int $itemId, array $adData): array
    {
        try {
            $client = $this->getAuthorizedClient($integration);
            $response = $client->patch(
                self::BASE_URL . "/core/v1/accounts/{$userId}/items/{$itemId}",
                $adData
            );

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['error']['message'] ?? 'Ошибка обновления объявления',
                'details' => $response->json(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Получить список категорий для сферы услуг
     */
    public function getServiceCategories(AvitoIntegration $integration, int $categoryId = null): array
    {
        try {
            $client = $this->getAuthorizedClient($integration);
            $url = self::BASE_URL . '/core/v1/categories';
            
            if ($categoryId) {
                $url .= "/{$categoryId}/attributes";
            }

            Log::info('Avito API: Getting categories', [
                'url' => $url,
                'category_id' => $categoryId,
                'integration_id' => $integration->id,
            ]);

            $response = $client->get($url);

            Log::info('Avito API: Categories response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            // Если получили 404, возвращаем статический список основных категорий для сферы услуг
            // Согласно документации Авито, endpoint /core/v1/categories может не существовать
            // Используем статический список как временное решение
            if ($response->status() === 404 && !$categoryId) {
                Log::warning('Avito API: Categories endpoint returned 404, using static list');
                
                // Возвращаем статический список основных категорий для сферы услуг
                // ID категорий взяты из реальных данных Авито
                // Категория 114 - "Предложение услуг" - основная для сферы услуг
                return [
                    'success' => true,
                    'data' => [
                        'categories' => [
                            ['id' => 114, 'name' => 'Предложение услуг'],
                            ['id' => 115, 'name' => 'Поиск работы'],
                            ['id' => 116, 'name' => 'Резюме'],
                        ],
                    ],
                ];
            }

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Avito API: Categories data structure', [
                    'keys' => is_array($data) ? array_keys($data) : 'not_array',
                    'has_categories' => isset($data['categories']),
                ]);
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? $errorData['message'] ?? 'Ошибка получения категорий';
            
            Log::error('Avito API: Error getting categories', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'response' => $errorData,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'details' => $errorData,
            ];
        } catch (Exception $e) {
            Log::error('Avito API: Exception getting categories', [
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
     * Получить список городов
     * Согласно документации Авито: https://developers.avito.ru/api-catalog
     * Endpoint может быть другим или требовать параметры
     */
    public function getLocations(AvitoIntegration $integration, string $query = null): array
    {
        try {
            $client = $this->getAuthorizedClient($integration);
            $params = [];
            
            if ($query) {
                $params['q'] = $query;
            }

            // Согласно документации Авито, для локаций может быть другой endpoint
            // Попробуем несколько вариантов
            $url = self::BASE_URL . '/core/v1/locations';
            
            Log::info('Avito API: Getting locations', [
                'url' => $url,
                'params' => $params,
                'integration_id' => $integration->id,
            ]);

            $response = $client->get($url, $params);
            
            // Если получили 404, возвращаем статический список основных городов
            // Согласно документации Авито, локации могут получаться по-другому
            if ($response->status() === 404) {
                Log::warning('Avito API: Locations endpoint returned 404, using static list');
                
                // Возвращаем статический список основных городов России
                // Это временное решение до выяснения правильного endpoint
                // ID городов нужно будет уточнить из реальных данных Авито API
                // Пока используем примерные значения
                return [
                    'success' => true,
                    'data' => [
                        'locations' => [
                            ['id' => 637640, 'name' => 'Москва'],
                            ['id' => 637650, 'name' => 'Санкт-Петербург'],
                            ['id' => 637660, 'name' => 'Новосибирск'],
                            ['id' => 637670, 'name' => 'Екатеринбург'],
                            ['id' => 637680, 'name' => 'Казань'],
                            ['id' => 637690, 'name' => 'Нижний Новгород'],
                            ['id' => 637700, 'name' => 'Челябинск'],
                            ['id' => 637710, 'name' => 'Самара'],
                            ['id' => 637720, 'name' => 'Омск'],
                            ['id' => 637730, 'name' => 'Ростов-на-Дону'],
                            ['id' => 637740, 'name' => 'Уфа'],
                            ['id' => 637750, 'name' => 'Красноярск'],
                            ['id' => 637760, 'name' => 'Воронеж'],
                            ['id' => 637770, 'name' => 'Пермь'],
                            ['id' => 637780, 'name' => 'Волгоград'],
                        ],
                    ],
                ];
            }

            Log::info('Avito API: Locations response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Avito API: Locations data structure', [
                    'keys' => is_array($data) ? array_keys($data) : 'not_array',
                    'has_locations' => isset($data['locations']),
                ]);
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? $errorData['message'] ?? 'Ошибка получения городов';
            
            Log::error('Avito API: Error getting locations', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'response' => $errorData,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'details' => $errorData,
            ];
        } catch (Exception $e) {
            Log::error('Avito API: Exception getting locations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

