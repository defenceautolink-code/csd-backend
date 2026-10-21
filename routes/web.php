<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

// Web-based All-in-One Cache & Optimize Clear
Route::get('/clear-cache', function () {
    Artisan::call('optimize:clear');

    return response()->json([
        'status' => true,
        'message' => 'All caches (Route, Config, Cache, View) cleared successfully!',
        'output' => trim(Artisan::output()),
    ]);
});

// Web-based Optimize Clear (php artisan optimize:clear)
Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');

    return response()->json([
        'status' => true,
        'message' => 'php artisan optimize:clear executed successfully!',
        'output' => trim(Artisan::output()),
    ]);
});

// Web-based Route Clear (php artisan route:clear)
Route::get('/route-clear', function () {
    Artisan::call('route:clear');

    return response()->json([
        'status' => true,
        'message' => 'php artisan route:clear executed successfully!',
        'output' => trim(Artisan::output()),
    ]);
});

// Web-based Config Clear Only (php artisan config:clear)
Route::get('/config-clear', function () {
    Artisan::call('config:clear');
    return response()->json([
        'status' => true,
        'message' => 'Config cache cleared successfully!',
        'output' => trim(Artisan::output()),
    ]);
});

// Web-based Application Cache Clear Only (php artisan cache:clear)
Route::get('/cache-clear', function () {
    Artisan::call('cache:clear');
    return response()->json([
        'status' => true,
        'message' => 'php artisan cache:clear executed successfully!',
        'output' => trim(Artisan::output()),
    ]);
});

// Web-based View Cache Clear Only (php artisan view:clear)
Route::get('/view-clear', function () {
    Artisan::call('view:clear');
    return response()->json([
        'status' => true,
        'message' => 'php artisan view:clear executed successfully!',
        'output' => trim(Artisan::output()),
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

