<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Input\Web\AdminDmcaController;
use App\Infrastructure\Adapters\Input\Web\FeedbackController;
use App\Infrastructure\Adapters\Input\Web\TranscribeVideoController;
use Illuminate\Support\Facades\Route;

// v1.0: all endpoints are public (no auth)
// Rate limiting: disabled in local dev, active in staging/production

$throttle = app()->environment('local') ? [] : ['throttle:30,1'];
$searchThrottle = app()->environment('local') ? [] : ['throttle:60,1'];
$adminThrottle = app()->environment('local') ? [] : ['throttle:10,1'];
$feedbackThrottle = app()->environment('local') ? [] : ['throttle:3,1'];

Route::post('/transcribe', [TranscribeVideoController::class, 'create'])
    ->middleware($throttle);
// anti-abuse: 30 req/min per IP; business quota (10 completed/month) checked in controller

Route::get('/transcribe/{id}', [TranscribeVideoController::class, 'status']);
Route::get('/transcribe/{id}/download', [TranscribeVideoController::class, 'download']);
Route::get('/search', [TranscribeVideoController::class, 'search'])
    ->middleware($searchThrottle);
Route::get('/history', [TranscribeVideoController::class, 'history']);
Route::get('/history/latest', [TranscribeVideoController::class, 'latest']);

// Feedback: send via Telegram (no auth, anti-abuse throttled)
Route::post('/feedback', [FeedbackController::class, 'send'])
    ->middleware($feedbackThrottle);

// Admin: DMCA takedown (protected by ADMIN_TOKEN env, no registration required)
Route::post('/admin/tasks/{id}/dmca-remove', [AdminDmcaController::class, 'remove'])
    ->middleware($adminThrottle);

// OpenAPI specification — generated from #[OA\*] attributes at runtime
Route::get('/docs/openapi.json', function () {
    $config = \App\Infrastructure\Adapters\Input\Web\OpenApi\OpenApiConfig::fromArray(config('openapi'));

    $generator = new \OpenApi\Generator();
    $openapi = $generator->generate($config->scanPaths);

    if ($openapi === null) {
        return response()->json(['error' => 'Failed to generate OpenAPI spec'], 500);
    }

    return response($openapi->toJson(), 200, [
        'Content-Type' => 'application/json',
    ])->header('Access-Control-Allow-Origin', '*');
})->name('api.openapi');

Route::get('/topics/popular', function () {
    $repo = app(\App\Application\Ports\Output\TaxonomyRepositoryInterface::class);
    $topics = $repo->paginateByType(\App\Domain\ValueObjects\TaxonomyType::Topic, 1, 8);
    return array_map(fn ($t) => [
        'name' => $t->name(),
        'slug' => $t->slug(),
        'video_count' => $t->videoCount(),
    ], $topics);
});
