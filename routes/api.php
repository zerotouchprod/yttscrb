<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Input\Web\TranscribeVideoController;
use Illuminate\Support\Facades\Route;

// v1.0: all endpoints are public (no auth)

Route::post('/transcribe', [TranscribeVideoController::class, 'create'])
    ->middleware('throttle:30,1');
// anti-abuse: 30 req/min per IP; business quota (10 completed/month) checked in controller

Route::get('/transcribe/{id}', [TranscribeVideoController::class, 'status']);
Route::get('/transcribe/{id}/download', [TranscribeVideoController::class, 'download']);
Route::get('/history', [TranscribeVideoController::class, 'history']);
Route::get('/history/latest', [TranscribeVideoController::class, 'latest']);
