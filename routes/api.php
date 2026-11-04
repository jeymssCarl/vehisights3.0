<?php

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/schedules/pending', [AutoSessionIngestController::class, 'pending']);
    Route::post('/auto-sessions/start', [AutoSessionIngestController::class, 'start']);
    Route::post('/auto-sessions/{autoSession}/complete', [AutoSessionIngestController::class, 'complete']);
});
