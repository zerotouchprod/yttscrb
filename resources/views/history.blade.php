<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">

    <title>Public Library — TubeSum</title>
    <meta name="description" content="Browse all recently transcribed YouTube videos with AI summaries on TubeSum. Free, no signup required.">
    <meta name="robots" content="noindex, follow">
    <link rel="canonical" href="{{ url('/history') }}">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">

    <!-- Header -->
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

        <h1 class="text-3xl font-bold text-white mb-8">Public Library</h1>

        @if (count($tasks) === 0)
            <div class="text-center py-16">
                <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <p class="text-gray-500 text-lg">No summaries yet.</p>
                <a href="/" class="mt-4 inline-block text-blue-400 hover:text-blue-300 transition-colors">
                    Transcribe your first video →
                </a>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($tasks as $task)
                    @php
                        $cardLink = ($task->slug() !== null && ! $task->isDmcaRemoved())
                            ? '/v/' . $task->slug()
                            : '/?task_id=' . $task->id();
                        $videoId = $task->youtubeUrl()->videoId()->value();
                        $thumbnailUrl = 'https://img.youtube.com/vi/' . $videoId . '/mqdefault.jpg';
                    @endphp
                    <a
                        href="{{ $cardLink }}"
                        class="block bg-gray-800/70 rounded-lg overflow-hidden border border-gray-700/40 hover:border-gray-600/60 transition-colors"
                    >
                        <div class="flex gap-3">
                            <div class="w-40 flex-shrink-0">
                                <div class="w-full aspect-video bg-gray-800 flex items-center justify-center relative overflow-hidden">
                                    <svg class="w-6 h-6 text-gray-600 absolute" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <img src="{{ $thumbnailUrl }}" alt="" class="w-full aspect-video object-cover relative z-10" loading="lazy" />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1 p-3 pl-0">
                                <span class="text-sm font-semibold text-blue-400 hover:text-blue-300 line-clamp-2 mb-1 block">{{ $task->title() ?? 'Untitled' }}</span>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                    @if ($task->durationSec() !== null)
                                        <span>{{ gmdate('G\h i\m', $task->durationSec()) }}</span>
                                    @endif
                                    @if ($task->completedAt() !== null)
                                        <span>{{ $task->completedAt()->format('M j, Y') }}</span>
                                    @endif
                                    <span class="text-blue-500 hover:text-blue-400 truncate">YouTube</span>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-8 flex items-center justify-center gap-2">
                @if ($paginator->onFirstPage())
                    <span class="px-3 py-2 rounded-lg text-sm text-gray-600 bg-gray-800/50 cursor-not-allowed">← Prev</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-2 rounded-lg text-sm text-gray-300 bg-gray-800 hover:bg-gray-700 transition-colors">← Prev</a>
                @endif

                @php
                    $currentPage = $paginator->currentPage();
                    $lastPage = $paginator->lastPage();
                    $start = max(1, $currentPage - 2);
                    $end = min($lastPage, $currentPage + 2);
                @endphp

                @if ($start > 1)
                    <a href="{{ $paginator->url(1) }}" class="px-3 py-2 rounded-lg text-sm text-gray-400 bg-gray-800 hover:bg-gray-700 transition-colors">1</a>
                    @if ($start > 2)
                        <span class="px-2 text-gray-600">…</span>
                    @endif
                @endif

                @for ($i = $start; $i <= $end; $i++)
                    @if ($i === $currentPage)
                        <span class="px-3 py-2 rounded-lg text-sm text-white bg-blue-600">{{ $i }}</span>
                    @else
                        <a href="{{ $paginator->url($i) }}" class="px-3 py-2 rounded-lg text-sm text-gray-400 bg-gray-800 hover:bg-gray-700 transition-colors">{{ $i }}</a>
                    @endif
                @endfor

                @if ($end < $lastPage)
                    @if ($end < $lastPage - 1)
                        <span class="px-2 text-gray-600">…</span>
                    @endif
                    <a href="{{ $paginator->url($lastPage) }}" class="px-3 py-2 rounded-lg text-sm text-gray-400 bg-gray-800 hover:bg-gray-700 transition-colors">{{ $lastPage }}</a>
                @endif

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-2 rounded-lg text-sm text-gray-300 bg-gray-800 hover:bg-gray-700 transition-colors">Next →</a>
                @else
                    <span class="px-3 py-2 rounded-lg text-sm text-gray-600 bg-gray-800/50 cursor-not-allowed">Next →</span>
                @endif
            </div>

            <p class="mt-4 text-center text-xs text-gray-600">
                Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} ({{ $paginator->total() }} total)
            </p>
        @endif

    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-800 py-8 mt-8">
        <div class="max-w-4xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-gray-600">
            <p>© {{ date('Y') }} TubeSum. AI-powered YouTube transcription service.</p>
            <div class="flex items-center gap-4">
                <a href="/" class="hover:text-gray-400 transition-colors">Transcribe a video</a>
                <a href="/history" class="hover:text-gray-400 transition-colors">Public Library</a>
                <a href="/dmca" class="hover:text-gray-400 transition-colors">Content Removal / DMCA</a>
            </div>
        </div>
    </footer>

</body>
</html>
