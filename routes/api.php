<?php

use App\Http\Controllers\Api\CameraController;
use App\Http\Controllers\Api\TokenAuthController;
use App\Http\Controllers\AutoSessionIngestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/login', [TokenAuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [TokenAuthController::class, 'logout']);
    Route::apiResource('cameras', CameraController::class)
        ->except('show')
        ->middleware('ability:camera:manage');

    Route::middleware('ability:auto-session:write')->group(function () {
        Route::get('/schedules/pending', [AutoSessionIngestController::class, 'pending']);
        Route::post('/auto-sessions/start', [AutoSessionIngestController::class, 'start']);
        Route::patch('/auto-sessions/{autoSession}/counts', [AutoSessionIngestController::class, 'updateCounts']);
        Route::post('/auto-sessions/{autoSession}/complete', [AutoSessionIngestController::class, 'complete']);
    });
});
