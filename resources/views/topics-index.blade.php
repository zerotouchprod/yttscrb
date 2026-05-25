<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>All Topics — TubeSum</title>
    <meta name="description" content="Browse all topics with free AI-powered video transcripts and summaries. No signup required.">
    <link rel="canonical" href="{{ url('/topics') }}">
    <meta property="og:title" content="All Topics — TubeSum">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen">
<div class="max-w-5xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="/" class="hover:text-white transition-colors">Home</a>
        <span class="mx-2">→</span>
        <span class="text-gray-200">Topics</span>
    </nav>

    <h1 class="text-3xl font-bold text-white mb-8">All Topics</h1>

    @if(empty($topics))
        <p class="text-gray-400 text-center py-12">No topics yet. Transcribe a video to get started!</p>
    @else
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($topics as $topic)
                <a href="{{ url('/topic/' . $topic->slug()) }}"
                   class="block bg-gray-800/60 rounded-xl p-4 border border-gray-700 hover:border-gray-500 hover:bg-gray-700/40 transition-all">
                    <span class="text-sm font-medium text-white block truncate">#{{ $topic->name() }}</span>
                    <span class="text-xs text-gray-500 mt-1">{{ $topic->videoCount() }} video{{ $topic->videoCount() !== 1 ? 's' : '' }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
</body>
</html>
