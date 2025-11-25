<?php

use App\Http\Controllers\DeployController;
use Illuminate\Support\Facades\Route;

// Роут для развертывания (должен быть перед catch-all)
Route::post('/deploy', [DeployController::class, 'deploy']);

// Единая точка входа для SPA
// Все маршруты обрабатываются Vue Router на клиенте
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
