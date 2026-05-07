<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Input\Web\TranscribeVideoController;
use Illuminate\Support\Facades\Route;

// v1.0: all endpoints are public (no auth)

Route::post('/transcribe', [TranscribeVideoController::class, 'create'])
    ->middleware('throttle:10,1440'); // 10 requests per day per IP (guest limit)

Route::get('/transcribe/{id}', [TranscribeVideoController::class, 'status']);
Route::get('/transcribe/{id}/download', [TranscribeVideoController::class, 'download']);
Route::get('/history', [TranscribeVideoController::class, 'history']);
Route::get('/history/latest', [TranscribeVideoController::class, 'latest']);
