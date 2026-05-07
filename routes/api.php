<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Input\Web\TranscribeVideoController;
use Illuminate\Support\Facades\Route;

Route::post('/transcribe', [TranscribeVideoController::class, 'create']);
Route::get('/transcribe/{id}', [TranscribeVideoController::class, 'status']);
Route::get('/transcribe/{id}/download', [TranscribeVideoController::class, 'download']);
