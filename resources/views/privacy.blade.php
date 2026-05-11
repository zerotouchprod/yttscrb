<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy — TubeSum</title>
    <meta name="description" content="Privacy Policy for TubeSum — how we collect, use, and protect your data.">
    <meta name="robots" content="noindex, follow">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="canonical" href="{{ url('/privacy') }}">

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
        <h1 class="text-3xl font-bold text-white mb-4">Privacy Policy</h1>
        <p class="text-gray-400 mb-2">Last updated: {{ date('F j, Y') }}</p>

        <div class="bg-gray-800/60 rounded-xl p-6 border border-gray-700/50 space-y-6 text-sm text-gray-300 leading-relaxed">

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">1. Data Collection</h2>
                <p>We collect only the YouTube video URLs you submit for transcription. We also store the generated transcripts, AI summaries, and processing timestamps. No personal accounts, emails, or passwords are required in the current version.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">2. Cookies & Analytics</h2>
                <p>The Service does not use tracking cookies for its core functionality. We use Yandex.Metrika on the landing page for anonymized visitor statistics. No personal data is shared with analytics providers.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">3. Data Storage</h2>
                <p>Transcripts and summaries are stored in a PostgreSQL database. Temporary audio files downloaded from YouTube are deleted immediately after processing. We do not store video or audio files permanently.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">4. Data Sharing</h2>
                <p>We do not sell, rent, or share your data with third parties, except for the AI providers (Groq, OpenAI) that process audio and generate summaries. These providers receive only the audio/text content needed for processing and do not retain it.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">5. Data Retention</h2>
                <p>Transcripts and summaries are retained indefinitely as part of the Public Library. You may request removal of specific content via our <a href="/dmca" class="text-blue-400 hover:text-blue-300">DMCA / Content Removal</a> process.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">6. GDPR Rights (EU Users)</h2>
                <p>If you are located in the European Union, you have the right to access, rectify, erase, and port your personal data. To exercise these rights, contact us at <a href="mailto:privacy@tubesum.app" class="text-blue-400 hover:text-blue-300">privacy@tubesum.app</a>.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">7. Contact</h2>
                <p>For privacy-related inquiries, contact us at <a href="mailto:privacy@tubesum.app" class="text-blue-400 hover:text-blue-300">privacy@tubesum.app</a> or visit our <a href="/contact" class="text-blue-400 hover:text-blue-300">Contact page</a>.</p>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-white mb-2">8. Changes to This Policy</h2>
                <p>We may update this Privacy Policy from time to time. Changes will be posted on this page. Continued use of the Service after changes constitutes acceptance of the revised policy.</p>
            </div>

        </div>

        <div class="mt-8 text-center">
            <a href="/" class="text-sm text-gray-500 hover:text-gray-300 transition-colors">← Back to TubeSum</a>
        </div>
    </main>

    @include('partials.footer')

</body>
</html>
