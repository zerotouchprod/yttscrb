<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Content Removal / DMCA — TubeSum</title>
    <meta name="description" content="Request removal of your content from TubeSum. We respond to all DMCA takedown requests promptly.">
    <meta name="robots" content="noindex, follow">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="canonical" href="{{ url('/dmca') }}">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">

    <!-- Header -->
    <header class="border-b border-gray-800">
        <div class="max-w-3xl mx-auto px-4 py-4">
            <a href="/" class="text-xl font-bold bg-gradient-to-r from-blue-400 to-blue-200 bg-clip-text text-transparent">
                TubeSum
            </a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-16">
        <h1 class="text-3xl font-bold text-white mb-4">Content Removal / DMCA</h1>
        <p class="text-gray-400 mb-8">
            TubeSum generates transcripts and summaries from publicly available YouTube videos using automatic speech recognition.
            We respect intellectual property rights and respond to all takedown requests promptly.
        </p>

        <div class="bg-gray-800/60 rounded-xl p-6 border border-gray-700/50 space-y-6">

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">How to request removal</h2>
                <p class="text-gray-300 text-sm leading-relaxed">
                    If you are the rights holder of a video and would like the transcript/summary removed from our site,
                    please send an email to <a href="mailto:dmca@tubesum.app" class="text-blue-400 hover:text-blue-300">dmca@tubesum.app</a> with:
                </p>
                <ul class="mt-3 space-y-2 text-sm text-gray-400 list-disc list-inside">
                    <li>The YouTube video URL or video ID</li>
                    <li>The URL of the TubeSum page you want removed (e.g. <code class="bg-gray-700 px-1 rounded">tubesum.app/v/your-video-slug</code>)</li>
                    <li>A brief statement confirming you are the rights holder or authorised representative</li>
                </ul>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">Response time</h2>
                <p class="text-gray-300 text-sm leading-relaxed">
                    We aim to process all valid requests within <strong>48 hours</strong>.
                    Once confirmed, the page will be permanently removed from our index and sitemap.
                </p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">What we do not host</h2>
                <p class="text-gray-300 text-sm leading-relaxed">
                    TubeSum does not host or distribute any video or audio files.
                    We only store text transcripts and AI-generated summaries generated from publicly available subtitles or speech recognition.
                </p>
            </div>

        </div>

        <div class="mt-8 text-center">
            <a href="/" class="text-sm text-gray-500 hover:text-gray-300 transition-colors">← Back to TubeSum</a>
        </div>
    </main>

</body>
</html>

