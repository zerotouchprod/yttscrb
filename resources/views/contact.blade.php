<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact — TubeSum</title>
    <meta name="description" content="Contact TubeSum — bug reports, feature requests, DMCA takedowns, and general inquiries.">
    <meta name="robots" content="noindex, follow">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="canonical" href="{{ url('/contact') }}">

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
        <h1 class="text-3xl font-bold text-white mb-4">Contact</h1>
        <p class="text-gray-400 mb-8">
            We're here to help. Reach out for bug reports, feature requests, or any questions about TubeSum.
        </p>

        <div class="bg-gray-800/60 rounded-xl p-6 border border-gray-700/50 space-y-6">

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">General Inquiries</h2>
                <p class="text-gray-300 text-sm leading-relaxed">
                    Email us at <a href="mailto:hello@tubesum.app" class="text-blue-400 hover:text-blue-300">hello@tubesum.app</a>.
                    We typically respond within 24 hours on business days.
                </p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">DMCA / Content Removal</h2>
                <p class="text-gray-300 text-sm leading-relaxed">
                    For copyright takedown requests, please use our <a href="/dmca" class="text-blue-400 hover:text-blue-300">DMCA page</a>
                    or email <a href="mailto:dmca@tubesum.app" class="text-blue-400 hover:text-blue-300">dmca@tubesum.app</a>.
                </p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">Bug Reports & Feature Requests</h2>
                <p class="text-gray-300 text-sm leading-relaxed">
                    Found a bug or have a feature idea? Email us at <a href="mailto:hello@tubesum.app" class="text-blue-400 hover:text-blue-300">hello@tubesum.app</a>
                    with as much detail as possible. Screenshots and the YouTube URL help us reproduce issues quickly.
                </p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">Follow Along</h2>
                <p class="text-gray-300 text-sm leading-relaxed">
                    Follow <a href="https://x.com/tubesum" target="_blank" rel="noopener noreferrer" class="text-blue-400 hover:text-blue-300">@tubesum on X (Twitter)</a>
                    for updates, new features, and service status.
                </p>
            </div>

        </div>

        <div class="mt-8 text-center">
            <a href="/" class="text-sm text-gray-500 hover:text-gray-300 transition-colors">← Back to TubeSum</a>
        </div>
    </main>

    @include('partials.footer')

</body>
</html>
