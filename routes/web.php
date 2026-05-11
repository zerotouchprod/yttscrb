<?php

use App\Infrastructure\Adapters\Input\Web\PublicTranscriptController;
use App\Infrastructure\Adapters\Input\Web\TranscribeVideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SEO landing pages: /v/{slug} → server-rendered transcript page for Google indexing.
Route::get('/v/{slug}', [PublicTranscriptController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+');

// Public history page with server-rendered pagination.
Route::get('/history', [TranscribeVideoController::class, 'historyPage']);

// DMCA / Content Removal info page
Route::get('/dmca', function () {
    return view('dmca');
});

// Terms of Service
Route::get('/terms', function () {
    return view('terms');
});

// Privacy Policy
Route::get('/privacy', function () {
    return view('privacy');
});

// Pricing page (beta)
Route::get('/pricing', function () {
    return view('pricing');
});

// Contact / Support page
Route::get('/contact', function () {
    return view('contact');
});
