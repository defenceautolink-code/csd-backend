<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

// Web-based Cache & Config Clear
Route::get('/clear-cache', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');

    return response()->json([
        'status' => true,
        'message' => 'Cache, Config, Route, and View cleared successfully!',
    ]);
});

// Web-based Config Clear Only
Route::get('/config-clear', function () {
    Artisan::call('config:clear');
    return response()->json([
        'status' => true,
        'message' => 'Config cache cleared successfully!',
    ]);
});

// Web-based Database Migration
Route::get('/run-migrate', function () {
    Artisan::call('migrate', ['--force' => true]);
    return response()->json([
        'status' => true,
        'message' => 'Database migrations executed successfully!',
        'output' => Artisan::output(),
    ]);
});

