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

// Единая точка входа для SPA
// Все маршруты обрабатываются Vue Router на клиенте
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
