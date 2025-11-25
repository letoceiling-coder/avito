<?php

use App\Http\Controllers\DeployController;
use App\Http\Controllers\LogsController;
use Illuminate\Support\Facades\Route;

// Роут для развертывания (без CSRF, защита через DEPLOY_TOKEN)
// Исключен из CSRF в bootstrap/app.php
Route::post('/deploy', [DeployController::class, 'deploy']);

// Роуты для просмотра логов (без CSRF, защита через DEPLOY_TOKEN)
Route::get('/logs', [LogsController::class, 'index']);
Route::get('/logs/list', [LogsController::class, 'list']);
Route::post('/logs/clear', [LogsController::class, 'clear']);

// Тестовый endpoint для проверки доступности callback
Route::get('/admin/avito/callback/test', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Callback route is accessible',
        'url' => url('/admin/avito/callback'),
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('avito.callback.test');

// Явный роут для callback Авито (должен быть перед catch-all)
// Это гарантирует, что callback страница всегда доступна
Route::get('/admin/avito/callback', function () {
    // Логируем запрос для диагностики
    \Log::info('Avito callback route accessed', [
        'url' => request()->fullUrl(),
        'query' => request()->query(),
        'user_agent' => request()->userAgent(),
    ]);
    
    return view('app');
})->name('avito.callback');

// Единая точка входа для SPA
// Все маршруты обрабатываются Vue Router на клиенте
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
