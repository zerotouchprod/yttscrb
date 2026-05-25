<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <title>All Topics — TubeSum</title>
    <meta name="description" content="Browse all topics with free AI-powered video transcripts and summaries. No signup required.">
    <link rel="canonical" href="{{ url('/topics') }}">
    <meta property="og:title" content="All Topics — TubeSum">

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
                <a href="/topics" class="text-blue-400 font-medium">Explore Topics</a>
                <a href="/" class="text-gray-400 hover:text-white transition-colors">← Transcribe</a>
            </nav>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-10">
        <nav class="text-sm text-gray-500 mb-6">
            <a href="/" class="hover:text-gray-300 transition-colors">Home</a>
            <span class="mx-2">→</span>
            <span class="text-gray-300">Topics</span>
        </nav>

        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">Explore Topics</h1>
            <p class="text-gray-400 mt-2">Discover summaries by category.</p>
        </div>

        @if(empty($topics))
            <div class="text-center py-16">
                <p class="text-gray-500 text-lg">No topics yet. Transcribe a video to get started!</p>
            </div>
        @else
            <div class="flex flex-wrap gap-3">
                @foreach($topics as $topic)
                    <a href="{{ url('/topic/' . $topic->slug()) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800/70 border border-gray-700/50 rounded-full hover:bg-gray-700 hover:border-blue-500/50 transition-all group">
                        <span class="text-sm text-gray-300 group-hover:text-white">{{ $topic->name() }}</span>
                        <span class="text-xs bg-gray-900/80 text-gray-500 px-2 py-0.5 rounded-full">
                            {{ $topic->videoCount() }}
                        </span>
                    </a>
                @endforeach
            </div>
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
