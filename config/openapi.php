<?php

declare(strict_types=1);

return [
    'title'       => env('APP_NAME', 'yttscrb') . ' API',
    'version'     => '1.0.0',
    'description' => 'YouTube Transcriber & Summarizer — Public API.',
    'servers'     => [
        ['url' => env('APP_URL', 'http://localhost'), 'description' => 'Current environment'],
    ],
    'scan_paths' => [
        app_path('Infrastructure/Adapters/Input/Web/Resources'),
        app_path('Infrastructure/Adapters/Input/Web/OpenApi'),
        app_path('Infrastructure/Adapters/Input/Web/TranscribeVideoController.php'),
    ],
    'output_path' => public_path('openapi.json'),
];
