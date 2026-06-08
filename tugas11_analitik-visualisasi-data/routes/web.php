<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WilayahController;
use App\Http\Controllers\ClassificationController;

// Route::get('/', [WilayahController::class, 'index']);
// Route::get('/get-kabupaten/{id}', [WilayahController::class, 'getKabupaten']);
// Route::get('/get-kecamatan/{id}', [WilayahController::class, 'getKecamatan']);
// Route::get('/get-kelurahan/{id}', [WilayahController::class, 'getKelurahan']);



Route::get('/', [ClassificationController::class, 'index']);
Route::post('/predict', [ClassificationController::class, 'predict']);