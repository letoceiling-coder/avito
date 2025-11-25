<?php

use Illuminate\Support\Facades\Route;

// Единая точка входа для SPA
// Все маршруты обрабатываются Vue Router на клиенте
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
