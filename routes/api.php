<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminMenuController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AvitoIntegrationController;
use App\Http\Controllers\Api\AvitoAdsController;
use App\Http\Controllers\Api\v1\FolderController;
use App\Http\Controllers\Api\v1\MediaController;
use Illuminate\Support\Facades\Route;

// Публичные роуты
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Защищённые роуты
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // Меню
    Route::get('/admin/menu', [AdminMenuController::class, 'index']);
    
    // Уведомления
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/all', [NotificationController::class, 'all']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    
    // Media API (v1)
    Route::prefix('v1')->group(function () {
        // Folders
        Route::get('folders/tree/all', [FolderController::class, 'tree'])->name('folders.tree');
        Route::post('folders/update-positions', [FolderController::class, 'updatePositions'])->name('folders.update-positions');
        Route::post('folders/{id}/restore', [FolderController::class, 'restore'])->name('folders.restore');
        Route::apiResource('folders', FolderController::class);
        
        // Media
        Route::post('media/{id}/restore', [MediaController::class, 'restore'])->name('media.restore');
        Route::delete('media/trash/empty', [MediaController::class, 'emptyTrash'])->name('media.trash.empty');
        Route::apiResource('media', MediaController::class);
        
        // Admin only routes (Roles and Users management)
        Route::middleware('admin')->group(function () {
            Route::apiResource('roles', RoleController::class);
            Route::apiResource('users', UserController::class);
        });
    });

    // Avito Integration API
    Route::prefix('avito')->group(function () {
        // Integration settings
        Route::get('integration', [AvitoIntegrationController::class, 'index']);
        Route::post('integration', [AvitoIntegrationController::class, 'store']);
        Route::put('integration/{id}', [AvitoIntegrationController::class, 'update']);
        Route::post('integration/tokens', [AvitoIntegrationController::class, 'storeTokens']);
        Route::get('integration/auth-url', [AvitoIntegrationController::class, 'getAuthUrl']);
        Route::post('integration/test', [AvitoIntegrationController::class, 'testConnection']);

        // Ads management
        Route::get('ads', [AvitoAdsController::class, 'index']);
        Route::get('ads/{itemId}', [AvitoAdsController::class, 'show']);
        Route::post('ads', [AvitoAdsController::class, 'store']);
        Route::post('ads/mass-create', [AvitoAdsController::class, 'massCreate']);
        Route::get('categories', [AvitoAdsController::class, 'getCategories']);
        Route::get('locations', [AvitoAdsController::class, 'getLocations']);
    });
});

