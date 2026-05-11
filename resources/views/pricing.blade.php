<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pricing — TubeSum</title>
    <meta name="description" content="TubeSum pricing — free beta with AI summaries. Pro plan coming soon with PDF export and unlimited transcriptions.">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="canonical" href="{{ url('/pricing') }}">

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
        <h1 class="text-3xl font-bold text-white mb-4 text-center">Pricing</h1>
        <p class="text-gray-400 mb-10 text-center">Simple, transparent pricing. Start free, no signup required.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl mx-auto">

            <!-- Free Beta -->
            <div class="bg-gray-800/60 rounded-xl p-6 border border-gray-700/50">
                <h2 class="text-xl font-bold text-white mb-1">Free Beta</h2>
                <p class="text-3xl font-bold text-blue-400 mb-4">$0</p>
                <ul class="space-y-2 text-sm text-gray-300 mb-6">
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-green-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        10 transcriptions / month
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-green-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        AI Summary
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-green-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Full Transcript
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-green-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        No Signup Required
                    </li>
                </ul>
                <a href="/" class="block text-center bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2.5 rounded-lg transition-colors text-sm">
                    Start Transcribing
                </a>
            </div>

            <!-- Pro (Coming Soon) -->
            <div class="bg-gray-800/40 rounded-xl p-6 border border-gray-700/30 opacity-80">
                <div class="flex items-center gap-2 mb-1">
                    <h2 class="text-xl font-bold text-white">Pro</h2>
                    <span class="text-[10px] bg-blue-900/50 border border-blue-700/50 text-blue-300 px-2 py-0.5 rounded-full">Coming Soon</span>
                </div>
                <p class="text-3xl font-bold text-gray-400 mb-4">TBA</p>
                <ul class="space-y-2 text-sm text-gray-400 mb-6">
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-gray-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Unlimited Transcriptions
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-gray-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Export to PDF
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-gray-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Priority Processing
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-gray-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        API Access
                    </li>
                </ul>
                <span class="block text-center bg-gray-700/50 text-gray-400 font-medium px-4 py-2.5 rounded-lg text-sm cursor-not-allowed">
                    Coming Soon
                </span>
            </div>

        </div>

        <div class="mt-8 text-center">
            <a href="/" class="text-sm text-gray-500 hover:text-gray-300 transition-colors">← Back to TubeSum</a>
        </div>
    </main>

    @include('partials.footer')

</body>
</html>
