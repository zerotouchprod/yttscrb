<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <title>{{ $taxonomy->name() }} Transcripts & AI Summaries — TubeSum</title>
    <meta name="description" content="Browse {{ $taxonomy->videoCount() }} video transcripts and AI summaries tagged '{{ $taxonomy->name() }}'. Free, no signup.">
    <link rel="canonical" href="{{ url('/' . $taxonomy->type()->routePrefix() . '/' . $taxonomy->slug()) }}">
    <meta property="og:title" content="{{ $taxonomy->name() }} — TubeSum">
    <meta property="og:description" content="Browse {{ $taxonomy->videoCount() }} video transcripts and AI summaries tagged '{{ $taxonomy->name() }}'.">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>body{font-family:ui-sans-serif,system-ui,sans-serif;}</style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">

    <header class="border-b border-gray-800 bg-gray-900/95 backdrop-blur sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/" class="text-xl font-bold bg-gradient-to-r from-blue-400 to-blue-200 bg-clip-text text-transparent">TubeSum</a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="/topics" class="text-gray-400 hover:text-white transition-colors">Explore Topics</a>
                <a href="/" class="text-gray-400 hover:text-white transition-colors">← Transcribe</a>
            </nav>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-10">
        <nav class="text-sm text-gray-500 mb-6">
            <a href="/" class="hover:text-gray-300 transition-colors">Home</a>
            <span class="mx-2">→</span>
            <a href="/topics" class="hover:text-gray-300 transition-colors">Topics</a>
            <span class="mx-2">→</span>
            <span class="text-gray-300">{{ $taxonomy->name() }}</span>
        </nav>

        <div class="mb-8 border-b border-gray-800 pb-6">
            <h1 class="text-4xl font-bold text-white flex items-center gap-3">
                <span class="text-blue-500">{{ $taxonomy->type()->value === 'topic' ? '#' : '🎙️' }}</span>
                {{ $taxonomy->name() }}
            </h1>
            <p class="mt-2 text-gray-400">
                Browse {{ $taxonomy->videoCount() }} AI summaries and transcripts tagged with this {{ $taxonomy->type()->value }}.
            </p>
        </div>

        @if(empty($tasks))
            <div class="text-center py-16">
                <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <p class="text-gray-500 text-lg">No transcripts yet for this {{ $taxonomy->type()->value }}.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($tasks as $task)
                    @php
                        $videoId = $task->video_id ?? '';
                        $thumbnailUrl = $videoId ? 'https://img.youtube.com/vi/' . $videoId . '/mqdefault.jpg' : '';
                    @endphp
                    <a href="{{ url('/v/' . $task->slug) }}"
                       class="block bg-gray-800/50 border border-gray-700/50 rounded-xl overflow-hidden hover:border-blue-500/50 transition-colors group">
                        <div class="aspect-video bg-gray-800 flex items-center justify-center relative overflow-hidden">
                            @if($thumbnailUrl)
                                <img src="{{ $thumbnailUrl }}" alt="" class="w-full h-full object-cover" loading="lazy">
                            @else
                                <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="p-4">
                            <h3 class="font-medium text-gray-200 group-hover:text-white line-clamp-2 text-sm leading-snug">
                                {{ $task->title ?? 'Untitled' }}
                            </h3>
                            <p class="text-xs text-gray-500 mt-2">
                                {{ $task->completed_at ? \Carbon\Carbon::parse($task->completed_at)->diffForHumans() : '' }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>

            @if($total > $perPage)
                <div class="flex justify-center gap-4 mt-8">
                    @if($page > 1)
                        <a href="?page={{ $page - 1 }}" class="px-4 py-2 text-sm bg-gray-800 border border-gray-700 rounded-lg text-gray-400 hover:text-white hover:border-gray-600 transition-colors">← Previous</a>
                    @endif
                    @if($page * $perPage < $total)
                        <a href="?page={{ $page + 1 }}" class="px-4 py-2 text-sm bg-gray-800 border border-gray-700 rounded-lg text-gray-400 hover:text-white hover:border-gray-600 transition-colors">Next →</a>
                    @endif
                </div>
            @endif
        @endif
    </main>

    <footer class="border-t border-gray-800 mt-16 py-8">
        <div class="max-w-5xl mx-auto px-4 text-center text-sm text-gray-600">
            <a href="/topics" class="hover:text-gray-400 transition-colors">All Topics</a>
            <span class="mx-3">·</span>
            <a href="/history" class="hover:text-gray-400 transition-colors">Public Library</a>
            <span class="mx-3">·</span>
            <a href="/" class="hover:text-gray-400 transition-colors">TubeSum</a>
        </div>
    </footer>
</body>
</html>
