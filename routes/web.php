<?php

use App\Infrastructure\Adapters\Input\Web\PublicTranscriptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SEO landing pages: /v/{slug} → server-rendered transcript page for Google indexing.
Route::get('/v/{slug}', [PublicTranscriptController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+');

// DMCA / Content Removal info page
Route::get('/dmca', function () {
    return view('dmca');
});
