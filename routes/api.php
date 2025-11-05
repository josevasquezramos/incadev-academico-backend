<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailableGroupController;
use App\Http\Controllers\Api\EnrollmentController;
use Illuminate\Support\Facades\Route;

// TODO: Borrar y usar el endpoint de Johan para el login
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('available-groups')->group(function () {
        Route::get('/', [AvailableGroupController::class, 'index']);
        Route::post('{group}/enroll', [EnrollmentController::class, 'enroll']);
    });
});
