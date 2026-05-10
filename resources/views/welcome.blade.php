<!DOCTYPE html>
<html lang="en" class="bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Primary SEO -->
    <title>TubeSum — Free YouTube Transcriber & AI Summarizer | Video to Text</title>
    <meta name="description" content="Free YouTube video to text transcription with AI-powered summary. No signup required. Extract subtitles, transcribe audio, and get instant summaries from any YouTube video.">
    <meta name="keywords" content="YouTube transcriber, YouTube transcription, video to text, YouTube summarizer, AI summary, speech to text, YouTube subtitle extractor, free transcription, транскрибация YouTube, расшифровка видео, суммаризация, текст из видео">
    <meta name="robots" content="index, follow">
    <meta name="author" content="TubeSum">
    <link rel="canonical" href="https://tubesum.app">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="TubeSum — Free YouTube Transcriber & AI Summarizer">
    <meta property="og:description" content="Free YouTube video to text transcription with AI-powered summary. No signup required. Extract subtitles, transcribe audio, and get instant summaries.">
    <meta property="og:url" content="https://tubesum.app">
    <meta property="og:site_name" content="TubeSum">
    <meta property="og:locale" content="en_US">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="TubeSum — Free YouTube Transcriber & AI Summarizer">
    <meta name="twitter:description" content="Free YouTube video to text transcription with AI-powered summary. No signup required.">

    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "TubeSum",
        "description": "Free YouTube video to text transcription with AI-powered summary. No signup required.",
        "url": "https://tubesum.app",
        "applicationCategory": "MultimediaApplication",
        "operatingSystem": "Web",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        },
        "browserRequirements": "Requires JavaScript"
    }
    </script>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
    <div id="app"></div>

    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=109136117', 'ym');

        ym(109136117, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/109136117" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
</body>
</html>
