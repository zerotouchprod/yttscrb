<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service — TubeSum</title>
    <meta name="description" content="Terms of Service for TubeSum — AI-powered YouTube transcription and summarization service.">
    <meta name="robots" content="noindex, follow">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="canonical" href="{{ url('/terms') }}">

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
        <h1 class="text-3xl font-bold text-white mb-4">Terms of Service</h1>
        <p class="text-gray-400 mb-2">Last updated: {{ date('F j, Y') }}</p>

        <div class="bg-gray-800/60 rounded-xl p-6 border border-gray-700/50 space-y-6 text-sm text-gray-300 leading-relaxed">

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">1. Acceptance of Terms</h2>
                <p>By accessing or using TubeSum ("the Service"), you agree to be bound by these Terms of Service. If you do not agree, do not use the Service.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">2. Service Description</h2>
                <p>TubeSum provides AI-powered transcription and summarization of publicly available YouTube videos. The Service extracts audio or subtitles from videos you submit and processes them using automatic speech recognition and language models.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">3. Fair Use & Rate Limiting</h2>
                <p>Free tier: 10 completed transcriptions per calendar month. We reserve the right to rate-limit or block excessive usage that degrades service for other users. Automated scraping or bulk processing is prohibited without prior written agreement.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">4. Intellectual Property</h2>
                <p>Transcripts and summaries are AI-generated derivative works. You retain rights to any content you own. TubeSum claims no ownership over YouTube videos processed through the Service. If you believe your copyright has been infringed, see our <a href="/dmca" class="text-blue-400 hover:text-blue-300">DMCA / Content Removal</a> page.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">5. DMCA & Content Removal</h2>
                <p>We respect intellectual property rights. Rights holders may request removal of transcripts and summaries via our <a href="/dmca" class="text-blue-400 hover:text-blue-300">DMCA page</a>. We aim to process valid requests within 48 hours.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">6. Disclaimer of Warranties</h2>
                <p>The Service is provided "as is" without warranties of any kind, express or implied. We do not guarantee the accuracy, completeness, or usefulness of transcripts and summaries. AI-generated content may contain errors or omissions.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">7. Limitation of Liability</h2>
                <p>To the fullest extent permitted by law, TubeSum shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising from your use of the Service.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">8. Changes to Terms</h2>
                <p>We may update these Terms from time to time. Changes will be posted on this page. Continued use of the Service after changes constitutes acceptance of the revised Terms.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">9. Governing Law</h2>
                <p>These Terms are governed by the laws of the Czech Republic. Any disputes shall be resolved in the courts of the Czech Republic, subject to applicable EU consumer protection laws.</p>
            </div>

        </div>

        <div class="mt-8 text-center">
            <a href="/" class="text-sm text-gray-500 hover:text-gray-300 transition-colors">← Back to TubeSum</a>
        </div>
    </main>

    @include('partials.footer')

</body>
</html>
