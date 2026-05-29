<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'yt_dlp_binary' => env('YT_DLP_BINARY', 'yt-dlp'),

    'youtube' => [
        'ipv6_prefix' => env('SERVICES_YOUTUBE_IPV6_PREFIX'),
        'cookies_path' => env('YT_DLP_COOKIES_PATH'),
        'yt_dlp_timeout' => (int) env('YT_DLP_TIMEOUT_SEC', 300),
        'cooldown_failure_threshold' => (int) env('YT_DLP_COOLDOWN_FAILURE_THRESHOLD', 3),
        'cooldown_duration_sec' => (int) env('YT_DLP_COOLDOWN_DURATION_SEC', 600),
        'cooldown_failure_window_sec' => (int) env('YT_DLP_COOLDOWN_FAILURE_WINDOW_SEC', 120),
        'retry_max_per_strategy' => (int) env('YT_DLP_RETRY_MAX_PER_STRATEGY', 2),
        'retry_cooldown_sec' => (int) env('YT_DLP_RETRY_COOLDOWN_SEC', 90),
        'transient_retry_cooldown_sec' => (int) env('YT_DLP_TRANSIENT_RETRY_COOLDOWN_SEC', 10),
    ],

    'max_video_duration_sec' => (int) env('MAX_VIDEO_DURATION_SEC', 7200),

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],
];
