<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">

    <!-- Primary SEO -->
    <title>{{ $task->title() }} — Transcript & AI Summary | TubeSum</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="index, follow">
    <meta name="author" content="TubeSum">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    <!-- Open Graph -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $task->title() }} — Transcript & AI Summary">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="TubeSum">
    <meta property="og:locale" content="en_US">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $task->title() }} — Transcript & AI Summary">
    <meta name="twitter:description" content="{{ $metaDescription }}">

    <!-- Structured Data: VideoObject -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "Article",
        "headline": {{ Js::from($task->title() . ' — Transcript & AI Summary') }},
        "description": {{ Js::from($metaDescription) }},
        "url": {{ Js::from($canonicalUrl) }},
        "datePublished": "{{ $task->completedAt()?->format('c') }}",
        "dateModified": "{{ $task->completedAt()?->format('c') }}",
        "publisher": {
            "@type": "Organization",
            "name": "TubeSum",
            "url": {{ Js::from(url('/')) }}
        },
        "mainEntityOfPage": {
            "@type": "WebPage",
            "@id": {{ Js::from($canonicalUrl) }}
        }
    }
    </script>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; }
        .prose-transcript { white-space: pre-wrap; line-height: 1.8; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">

    <!-- Header / Nav -->
    <header class="border-b border-gray-800 bg-gray-900/95 backdrop-blur sticky top-0 z-40">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/" class="text-xl font-bold bg-gradient-to-r from-blue-400 to-blue-200 bg-clip-text text-transparent">
                TubeSum
            </a>
            <a href="/" class="text-sm text-gray-400 hover:text-white transition-colors">
                ← Transcribe a video
            </a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-10">

        <!-- Video Info -->
        <div class="mb-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-3 leading-tight">
                {{ $task->title() }}
            </h1>
            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                @if ($task->durationSec() !== null)
                    <span>
                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ gmdate('G\h i\m', $task->durationSec()) }} video
                    </span>
                @endif
                @if ($task->completedAt() !== null)
                    <span>Transcribed {{ $task->completedAt()->format('M j, Y') }}</span>
                @endif
                <a
                    href="https://www.youtube.com/watch?v={{ $task->youtubeUrl()->videoId()->value() }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-red-400 hover:text-red-300 transition-colors"
                >
                    Watch on YouTube ↗
                </a>
            </div>
        </div>

        <!-- AI Summary -->
        @if ($task->summary() !== null)
            <section class="bg-blue-900/20 border border-blue-700/40 rounded-xl p-6 mb-8">
                <h2 class="text-xl font-semibold text-blue-300 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    AI Summary
                </h2>
                <div class="text-gray-200 leading-relaxed prose-transcript">{{ $task->summary() }}</div>
            </section>
        @endif

        <!-- Full Transcript -->
        @if ($task->resultText() !== null)
            <section class="mb-10">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-200">Full Transcript</h2>
                    <a
                        href="/api/transcribe/{{ $task->id() }}/download"
                        class="text-sm px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-300 transition-colors flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download .txt
                    </a>
                </div>
                <div class="bg-gray-800/60 rounded-xl p-6 border border-gray-700/50 text-gray-300 text-sm prose-transcript leading-relaxed max-h-[600px] overflow-y-auto">
                    {{ $task->resultText()->value() }}
                </div>
            </section>
        @endif

    </main>

    <!-- CTA Banner — sticky bottom, converts search visitors into users -->
    <div class="fixed bottom-0 inset-x-0 z-50 bg-gradient-to-r from-blue-700 to-blue-600 shadow-2xl" id="cta-banner">
        <div class="max-w-4xl mx-auto px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-white text-sm font-medium text-center sm:text-left">
                ⚡ Saved you <strong>{{ $task->durationSec() !== null ? gmdate('G\h i\m', $task->durationSec()) : 'time' }}</strong> reading this?
                Transcribe any YouTube video for free — no signup needed.
            </p>
            <div class="flex items-center gap-3 flex-shrink-0">
                <a
                    href="/"
                    class="bg-white text-blue-700 font-semibold text-sm px-5 py-2 rounded-full hover:bg-blue-50 transition-colors whitespace-nowrap"
                >
                    Try it now →
                </a>
                <button
                    onclick="document.getElementById('cta-banner').remove()"
                    class="text-blue-200 hover:text-white transition-colors p-1"
                    aria-label="Dismiss"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Spacer for fixed CTA banner -->
    <div class="h-20"></div>

    @include('partials.footer')

</body>
</html>

