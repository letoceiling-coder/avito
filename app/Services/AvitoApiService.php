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
            $this->refreshIntegrationToken($integration);
        }

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
     */
    public function getAds(AvitoIntegration $integration, int $userId, array $params = []): array
    {
        try {
            $client = $this->getAuthorizedClient($integration);
            $url = self::BASE_URL . "/core/v1/accounts/{$userId}/items";
            
            Log::info('Avito API: Getting ads', [
                'user_id' => $userId,
                'url' => $url,
                'params' => $params,
            ]);
            
            $response = $client->get($url, $params);

            Log::info('Avito API: Get ads response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? $errorData['message'] ?? 'Ошибка получения объявлений';
            
            Log::error('Avito API: Error getting ads', [
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
     */
    public function createAd(AvitoIntegration $integration, int $userId, array $adData): array
    {
        try {
            $client = $this->getAuthorizedClient($integration);
            $response = $client->post(
                self::BASE_URL . "/core/v1/accounts/{$userId}/items",
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
                'error' => $response->json()['error']['message'] ?? 'Ошибка создания объявления',
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

            $response = $client->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['error']['message'] ?? 'Ошибка получения категорий',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Получить список городов
     */
    public function getLocations(AvitoIntegration $integration, string $query = null): array
    {
        try {
            $client = $this->getAuthorizedClient($integration);
            $params = [];
            
            if ($query) {
                $params['q'] = $query;
            }

            $response = $client->get(self::BASE_URL . '/core/v1/locations', $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['error']['message'] ?? 'Ошибка получения городов',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

