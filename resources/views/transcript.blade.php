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
    <meta property="og:type" content="video.other">
    <meta property="og:title" content="{{ $task->title() }} — Transcript & AI Summary | TubeSum">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @if ($task->youtubeUrl()?->videoId()?->value() !== null)
        <meta property="og:image" content="https://img.youtube.com/vi/{{ $task->youtubeUrl()->videoId()->value() }}/mqdefault.jpg">
        <meta property="og:image:width" content="320">
        <meta property="og:image:height" content="180">
        <meta property="og:video" content="https://www.youtube.com/embed/{{ $task->youtubeUrl()->videoId()->value() }}">
        <meta property="og:video:type" content="text/html">
        <meta property="og:video:width" content="1280">
        <meta property="og:video:height" content="720">
    @endif
    <meta property="og:site_name" content="TubeSum">
    <meta property="og:locale" content="en_US">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $task->title() }} — Transcript & AI Summary">
    <meta name="twitter:description" content="{{ $metaDescription }}">

    <!-- Structured Data: VideoObject -->
    @php
        $vid = $task->youtubeUrl()?->videoId()?->value();
        $durationSec = $task->durationSec() ?? 0;

        // Build ISO 8601 duration string
        $h = intdiv($durationSec, 3600);
        $m = intdiv($durationSec % 3600, 60);
        $s = $durationSec % 60;
        $isoDuration = 'PT' . ($h > 0 ? $h . 'H' : '') . ($m > 0 ? $m . 'M' : '') . $s . 'S';

        // Helper: convert "MM:SS" or "HH:MM:SS" to seconds
        $toSeconds = function(string $tc): int {
            $parts = explode(':', $tc);
            if (count($parts) === 3) {
                return (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
            }
            return (int)$parts[0] * 60 + (int)$parts[1];
        };

        // Build hasPart clips from keyPoints and tutorialSteps
        $hasPart = [];

        // Introduction clip (0 until first keyPoint or 120s)
        $firstKpTime = null;
        if ($renderedSummary !== null && count($renderedSummary->keyPoints()) > 0) {
            $firstKpTime = $toSeconds($renderedSummary->keyPoints()[0]->timecode);
        }
        $introEnd = $firstKpTime !== null ? $firstKpTime : min($durationSec, 120);
        $hasPart[] = [
            '@type' => 'Clip',
            'name' => 'Introduction: ' . \Illuminate\Support\Str::limit($task->title(), 80),
            'startOffset' => 0,
            'endOffset' => $introEnd,
            'url' => $vid ? 'https://www.youtube.com/watch?v=' . $vid . '&t=0' : null,
        ];

        // Key Points as Clips
        if ($renderedSummary !== null) {
            foreach ($renderedSummary->keyPoints() as $kp) {
                $start = $toSeconds($kp->timecode);
                $end = $start + 120; // estimate: 2 min per point
                if ($durationSec > 0) {
                    $end = min($end, $durationSec);
                }
                $hasPart[] = [
                    '@type' => 'Clip',
                    'name' => 'Key Point: ' . \Illuminate\Support\Str::limit($kp->title, 90),
                    'startOffset' => $start,
                    'endOffset' => $end,
                    'url' => $vid ? 'https://www.youtube.com/watch?v=' . $vid . '&t=' . $start : null,
                ];
            }

            // Tutorial Steps as Clips
            foreach ($renderedSummary->tutorialSteps() as $step) {
                if ($step->time === '') continue;
                $start = $toSeconds($step->time);
                $end = $start + 120;
                if ($durationSec > 0) {
                    $end = min($end, $durationSec);
                }
                $hasPart[] = [
                    '@type' => 'Clip',
                    'name' => 'Step ' . $step->step . ': ' . \Illuminate\Support\Str::limit($step->action, 90),
                    'startOffset' => $start,
                    'endOffset' => $end,
                    'url' => $vid ? 'https://www.youtube.com/watch?v=' . $vid . '&t=' . $start : null,
                ];
            }
        }

        // Convert to JSON for ld+json
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $task->title(),
            'description' => $metaDescription,
            'thumbnailUrl' => $vid ? 'https://img.youtube.com/vi/' . $vid . '/mqdefault.jpg' : null,
            'uploadDate' => $task->completedAt()?->format('c'),
            'duration' => $isoDuration,
            'contentUrl' => $vid ? 'https://www.youtube.com/watch?v=' . $vid : null,
            'embedUrl' => $vid ? 'https://www.youtube.com/embed/' . $vid : null,
            'url' => $canonicalUrl,
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'TubeSum',
                'url' => url('/'),
            ],
            'hasPart' => $hasPart,
        ];

        // Remove null values
        $schema = array_filter($schema, fn($v) => $v !== null);
    @endphp
    <script type="application/ld+json">
    {!! json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
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
        <div class="mb-8 flex gap-6 sm:gap-8 items-start">
            @if ($task->youtubeUrl() !== null)
                <img
                    src="https://img.youtube.com/vi/{{ $task->youtubeUrl()->videoId()->value() }}/mqdefault.jpg"
                    alt="{{ $task->title() }} thumbnail"
                    class="w-[180px] sm:w-[220px] flex-shrink-0 rounded-lg object-cover bg-gray-700 hidden sm:block"
                    style="aspect-ratio: 16/9"
                    loading="lazy"
                />
            @endif
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-3 leading-tight">
                    {{ $task->title() }}
                </h1>
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                    @if ($task->durationSec() > 0)
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

                {{-- Content Meta --}}
                @if ($renderedSummary !== null && $renderedSummary->contentMeta() !== null)
                    @php $meta = $renderedSummary->contentMeta(); @endphp
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        @php
                            $complexityColors = [
                                'beginner' => 'text-green-400 bg-green-500/10',
                                'intermediate' => 'text-amber-400 bg-amber-500/10',
                                'advanced' => 'text-orange-400 bg-orange-500/10',
                                'expert' => 'text-red-400 bg-red-500/10',
                            ];
                            $complexityClass = $complexityColors[$meta->complexity] ?? 'text-gray-400 bg-gray-500/10';
                        @endphp
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full {{ $complexityClass }}">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            {{ ucfirst($meta->complexity) }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-400 bg-gray-800 rounded-full">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $meta->readingTimeMinutes }} min read
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-400 bg-gray-800 rounded-full cursor-help" title="{{ $meta->targetAudience }}">
                            For: {{ $meta->targetAudience }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <!-- AI Summary -->
        @if ($renderedSummary !== null)
            <section class="bg-blue-900/20 border border-blue-700/40 rounded-xl p-6 mb-8">
                <h2 class="text-xl font-semibold text-blue-300 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    AI Summary
                </h2>

                <!-- Introduction -->
                <p class="text-gray-200 leading-relaxed mb-4">
                    {{ $renderedSummary->introduction() }}
                </p>

                <!-- Key Points -->
                @if (count($renderedSummary->keyPoints()) > 0)
                    <div class="space-y-3 mb-4">
                        @foreach ($renderedSummary->keyPoints() as $point)
                            <div class="bg-blue-950/30 rounded-lg p-3 border border-blue-800/30">
                                <div class="flex items-start gap-2">
                                    @if ($task->youtubeUrl() !== null)
                                        @php
                                            $parts = explode(':', $point->timecode);
                                            $sec = count($parts) === 3
                                                ? (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2]
                                                : (int)$parts[0] * 60 + (int)$parts[1];
                                        @endphp
                                        <a href="https://youtube.com/watch?v={{ $task->youtubeUrl()->videoId()->value() }}&t={{ $sec }}"
                                           target="_blank" rel="noopener noreferrer"
                                           class="text-blue-400 hover:text-blue-300 font-mono text-xs mt-0.5 shrink-0 transition-colors"
                                           title="Open YouTube at {{ $point->timecode }}">
                                            [{{ $point->timecode }}]
                                        </a>
                                    @endif
                                    <div>
                                        <strong class="text-gray-100 text-sm">{{ $point->title }}</strong>
                                        <p class="text-gray-400 text-sm mt-0.5">{{ $point->details }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Conclusion -->
                @if ($renderedSummary->conclusion() !== null)
                    <p class="text-gray-300 leading-relaxed italic border-t border-blue-800/30 pt-3 mt-4">
                        {{ $renderedSummary->conclusion() }}
                    </p>
                @endif
            </section>
        @endif

        <!-- Clickbait Verdict -->
        @if ($renderedSummary !== null && $renderedSummary->clickbaitVerdict() !== null)
            @php $verdict = $renderedSummary->clickbaitVerdict(); @endphp
            <section class="bg-gray-800/60 rounded-xl p-5 border border-gray-700 mb-8">
                <div class="flex items-center gap-3 mb-3">
                    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Clickbait Check</h2>
                    @php
                        $scoreColor = $verdict->score >= 80 ? 'bg-green-900/60 text-green-400 border-green-700/40'
                            : ($verdict->score >= 50 ? 'bg-yellow-900/60 text-yellow-400 border-yellow-700/40'
                            : 'bg-red-900/60 text-red-400 border-red-700/40');
                        $gaugeColor = $verdict->score >= 80 ? 'bg-gradient-to-r from-green-600 to-green-400'
                            : ($verdict->score >= 50 ? 'bg-gradient-to-r from-yellow-600 to-yellow-400'
                            : 'bg-gradient-to-r from-red-600 to-red-400');
                    @endphp
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full border {{ $scoreColor }}">
                        {{ $verdict->score }}% Legit
                    </span>
                </div>
                <div class="h-2 bg-gray-700 rounded-full overflow-hidden mb-3">
                    <div class="h-full rounded-full {{ $gaugeColor }}" style="width: {{ $verdict->score }}%"></div>
                </div>
                <p class="text-gray-300 text-sm leading-relaxed italic">
                    "{{ $verdict->comment }}"
                </p>
            </section>
        @endif

        <!-- Resources -->
        @if ($renderedSummary !== null && count($renderedSummary->resources()) > 0)
            <section class="mb-8">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2 mb-3">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                    Mentioned in this Video
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($renderedSummary->resources() as $resource)
                        @php
                            $typeIcons = [
                                'book' => '<svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
                                'tool' => '<svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                                'service' => '<svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>',
                                'person' => '<svg class="w-3.5 h-3.5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                                'link' => '<svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>',
                            ];
                            $icon = $typeIcons[$resource->type] ?? $typeIcons['link'];
                        @endphp
                        @php
                            $resourceHref = $resource->url !== null
                                ? $resource->url
                                : 'https://www.google.com/search?q=' . urlencode($resource->name);
                        @endphp
                        <a href="{{ $resourceHref }}" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-2.5 rounded-lg p-2.5 border border-gray-700 bg-gray-800/60 hover:border-emerald-500/40 hover:bg-gray-800 transition-all text-sm">
                            <span class="shrink-0 w-7 h-7 flex items-center justify-center rounded-md bg-gray-700/60 text-xs">
                                {!! $icon !!}
                            </span>
                            <div class="min-w-0">
                                <p class="text-gray-200 font-medium truncate">{{ $resource->name }}</p>
                                <p class="text-gray-500 text-xs capitalize">{{ $resource->type }}</p>
                            </div>
                            <svg class="w-3 h-3 text-gray-600 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Tutorial Checklist -->
        @if ($renderedSummary !== null && count($renderedSummary->tutorialSteps()) > 0)
            <section class="mb-8">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2 mb-3">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Tutorial Checklist
                </h2>
                <div class="space-y-2">
                    @foreach ($renderedSummary->tutorialSteps() as $step)
                        <div class="flex items-start gap-3 rounded-lg p-3 border border-gray-700/40 bg-gray-800/30">
                            <span class="shrink-0 text-xs font-mono font-medium text-gray-400 w-5 text-center">
                                {{ $step->step }}
                            </span>
                            @if ($step->time !== '' && $task->youtubeUrl() !== null)
                                @php
                                    $stepParts = explode(':', $step->time);
                                    $stepSec = count($stepParts) === 3
                                        ? (int)$stepParts[0] * 3600 + (int)$stepParts[1] * 60 + (int)$stepParts[2]
                                        : (int)$stepParts[0] * 60 + (int)$stepParts[1];
                                @endphp
                                <a
                                    href="https://youtube.com/watch?v={{ $task->youtubeUrl()->videoId()->value() }}&t={{ $stepSec }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="shrink-0 text-xs font-mono font-medium text-blue-400 hover:text-blue-300 bg-blue-500/10 hover:bg-blue-500/25 rounded border border-blue-500/30 px-1.5 py-0.5 transition-colors"
                                    title="Open YouTube at {{ $step->time }}"
                                >{{ $step->time }}</a>
                            @endif
                            <span class="text-sm leading-relaxed text-gray-200">
                                {{ $step->action }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Flashcards --}}
        @if ($renderedSummary !== null && count($renderedSummary->flashCards()) > 0)
            <section class="mb-8">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        Study Flashcards ({{ count($renderedSummary->flashCards()) }})
                    </h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($renderedSummary->flashCards() as $card)
                        <details class="group rounded-lg border border-gray-700 bg-gray-800/50 hover:border-purple-500 transition-colors">
                            <summary class="flex items-start justify-between gap-2 p-4 cursor-pointer list-none">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-200">{{ $card->question }}</p>
                                    <div class="flex items-center gap-2 mt-2">
                                        @php
                                            $diffColors = ['easy' => 'text-green-400 bg-green-500/10', 'medium' => 'text-amber-400 bg-amber-500/10', 'hard' => 'text-red-400 bg-red-500/10'];
                                            $diffClass = $diffColors[$card->difficulty] ?? 'text-gray-400 bg-gray-500/10';
                                        @endphp
                                        <span class="text-xs capitalize px-1.5 py-0.5 rounded {{ $diffClass }}">{{ $card->difficulty }}</span>
                                        <span class="text-xs text-gray-500">Click to reveal answer</span>
                                    </div>
                                </div>
                                <svg class="w-4 h-4 text-gray-600 shrink-0 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="px-4 pb-4 border-t border-gray-700/50 pt-3">
                                <p class="text-sm text-gray-200 leading-relaxed">{{ $card->answer }}</p>
                                <p class="mt-2 text-xs text-purple-400">{{ $card->sourceTimecode }}</p>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Highlights --}}
        @if ($renderedSummary !== null && count($renderedSummary->highlights()) > 0)
            <section class="mb-8">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2 mb-3">
                    <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                    🔥 Best Moments
                </h2>
                <div class="space-y-2">
                    @foreach ($renderedSummary->highlights() as $moment)
                        @php
                            $emojiMap = ['surprise' => '😲', 'insight' => '💡', 'humor' => '😂', 'revelation' => '🤯', 'quote' => '💬'];
                            $emoji = $emojiMap[$moment->category] ?? '⭐';
                        @endphp
                        <div class="flex items-start gap-3 rounded-lg p-3 border border-gray-700 bg-gray-800/50">
                            <span class="shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-amber-500/10 text-base">{{ $emoji }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-200">{{ $moment->title }}</p>
                                <p class="mt-1 text-xs text-gray-400 leading-relaxed">{{ $moment->whyNotable }}</p>
                                <span class="inline-block mt-2 text-xs text-amber-400 font-mono">{{ $moment->timecode }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Full Transcript -->
        @if (count($transcriptChunks) > 0)
            <section class="mb-10">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Full Transcript
                    </h2>
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
                <div class="bg-gray-800/60 rounded-xl p-6 border border-gray-700/50 text-gray-300 text-sm leading-relaxed max-h-[600px] overflow-y-auto">
                    @foreach ($transcriptChunks as $chunk)
                        <p class="mb-3 last:mb-0 break-words">
                            @if ($chunk['timeSec'] !== null && $task->youtubeUrl() !== null)
                                @php
                                    $sec = $chunk['timeSec'];
                                    $h = intdiv($sec, 3600);
                                    $m = intdiv($sec % 3600, 60);
                                    $s = $sec % 60;
                                    $timecode = $h > 0
                                        ? sprintf('%d:%02d:%02d', $h, $m, $s)
                                        : sprintf('%02d:%02d', $m, $s);
                                @endphp
                                <a
                                    href="https://youtube.com/watch?v={{ $task->youtubeUrl()->videoId()->value() }}&t={{ $chunk['timeSec'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-block text-blue-400 hover:text-blue-300 font-mono text-xs mr-2 transition-colors shrink-0"
                                    title="Open YouTube at {{ $timecode }}"
                                >[{{ $timecode }}]</a>
                            @endif
                            {{ $chunk['text'] }}
                        </p>
                    @endforeach
                </div>
            </section>
        @endif

    </main>

    <!-- CTA Banner — sticky bottom, converts search visitors into users -->
    <div class="fixed bottom-0 inset-x-0 z-50 bg-gradient-to-r from-blue-700 to-blue-600 shadow-2xl" id="cta-banner">
        <div class="max-w-4xl mx-auto px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-white text-sm font-medium text-center sm:text-left">
                ⚡ Saved you <strong>{{ $task->durationSec() > 0 ? gmdate('G\h i\m', $task->durationSec()) : 'time' }}</strong> reading this?
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

