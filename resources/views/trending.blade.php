<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- SEO -->
    <title>Trending Transcripts This Week — TubeSum</title>
    <meta name="description" content="The most-read AI transcripts and summaries on TubeSum this week. Discover what people are reading right now.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ $canonical }}">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Trending Transcripts This Week — TubeSum">
    <meta property="og:description" content="The most-read AI transcripts and summaries on TubeSum this week.">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:site_name" content="TubeSum">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>body { font-family: ui-sans-serif, system-ui, sans-serif; }</style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">

    <!-- Header -->
    <header class="border-b border-gray-800 bg-gray-900/95 backdrop-blur sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/" class="text-xl font-bold bg-gradient-to-r from-blue-400 to-blue-200 bg-clip-text text-transparent">
                TubeSum
            </a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="/topics" class="text-gray-400 hover:text-white transition-colors">Topics</a>
                <a href="/history" class="text-gray-400 hover:text-white transition-colors">History</a>
                <a href="/" class="text-gray-400 hover:text-white transition-colors">← Transcribe</a>
            </nav>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-10">

        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-500 mb-6">
            <a href="/" class="hover:text-gray-300 transition-colors">Home</a>
            <span class="mx-2">→</span>
            <span class="text-gray-300">Trending</span>
        </nav>

        <!-- Page header -->
        <div class="mb-8 border-b border-gray-800 pb-6">
            <h1 class="text-4xl font-bold text-white flex items-center gap-3">
                <span class="text-orange-400">🔥</span>
                Trending This Week
            </h1>
            <p class="mt-2 text-gray-400">
                The most-read transcripts on TubeSum right now. Resets every Monday.
            </p>
        </div>

        @if(empty($tasks))
            <!-- Empty state: shown until first views accumulate -->
            <div class="text-center py-20">
                <div class="text-6xl mb-4">📭</div>
                <p class="text-gray-400 text-lg mb-2">No trending data yet.</p>
                <p class="text-gray-500 text-sm">Check back after a few transcripts have been read.</p>
                <a href="/history" class="mt-6 inline-block px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition-colors">
                    Browse all transcripts →
                </a>
            </div>
        @else
            <!-- Top 3 — highlighted podium -->
            @if(count($tasks) >= 1)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    @foreach(array_slice($tasks, 0, 3) as $index => $task)
                        @php
                            $medals  = ['🥇', '🥈', '🥉'];
                            $borders = ['border-yellow-500/60', 'border-gray-400/40', 'border-orange-600/40'];
                            $medal   = $medals[$index] ?? '';
                            $border  = $borders[$index] ?? 'border-gray-700/50';
                            $videoId = $task->youtubeUrl()->videoId()->value();
                        @endphp
                        <a href="{{ url('/v/' . $task->slug()) }}"
                           class="block bg-gray-800/60 border {{ $border }} rounded-xl overflow-hidden hover:border-blue-500/50 transition-colors group">
                            <div class="aspect-video bg-gray-800 relative overflow-hidden">
                                <img
                                    src="https://img.youtube.com/vi/{{ $videoId }}/mqdefault.jpg"
                                    alt="{{ $task->title() }}"
                                    class="w-full h-full object-cover"
                                    loading="lazy"
                                >
                                <span class="absolute top-2 left-2 text-2xl leading-none">{{ $medal }}</span>
                                <span class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-2 py-0.5 rounded-full">
                                    👁 {{ number_format($task->viewsCount()) }}
                                </span>
                            </div>
                            <div class="p-4">
                                <h2 class="font-semibold text-gray-100 group-hover:text-white line-clamp-2 text-sm leading-snug">
                                    {{ $task->title() ?? 'Untitled' }}
                                </h2>
                                @if($task->completedAt() !== null)
                                    <p class="text-xs text-gray-500 mt-2">
                                        {{ $task->completedAt()->format('M j, Y') }}
                                    </p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            <!-- Positions 4–20 -->
            @if(count($tasks) > 3)
                <div class="space-y-3">
                    @foreach(array_slice($tasks, 3) as $index => $task)
                        @php
                            $rank    = $index + 4;
                            $videoId = $task->youtubeUrl()->videoId()->value();
                        @endphp
                        <a href="{{ url('/v/' . $task->slug()) }}"
                           class="flex items-center gap-4 bg-gray-800/40 border border-gray-700/40 rounded-xl p-4 hover:border-blue-500/40 hover:bg-gray-800/60 transition-colors group">
                            <span class="text-gray-600 font-mono text-sm w-6 text-center flex-shrink-0">{{ $rank }}</span>
                            <img
                                src="https://img.youtube.com/vi/{{ $videoId }}/mqdefault.jpg"
                                alt=""
                                class="w-20 h-[45px] object-cover rounded flex-shrink-0 bg-gray-700"
                                loading="lazy"
                            >
                            <div class="min-w-0 flex-1">
                                <h3 class="font-medium text-gray-200 group-hover:text-white line-clamp-1 text-sm">
                                    {{ $task->title() ?? 'Untitled' }}
                                </h3>
                                @if($task->completedAt() !== null)
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $task->completedAt()->format('M j, Y') }}
                                    </p>
                                @endif
                            </div>
                            <span class="text-xs text-gray-500 flex-shrink-0 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                {{ number_format($task->viewsCount()) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        @endif

    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-800 mt-16 py-8 text-center text-xs text-gray-600">
        <p>
            <a href="/" class="hover:text-gray-400 transition-colors">TubeSum</a>
            · <a href="/privacy" class="hover:text-gray-400 transition-colors">Privacy</a>
            · <a href="/terms" class="hover:text-gray-400 transition-colors">Terms</a>
            · <a href="/dmca" class="hover:text-gray-400 transition-colors">DMCA</a>
        </p>
        <p class="mt-2">Trending resets every Monday at 00:00 UTC.</p>
    </footer>

</body>
</html>

