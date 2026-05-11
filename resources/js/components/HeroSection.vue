<template>
  <header class="text-center space-y-4 mb-10 px-4">
    <h1 class="text-4xl sm:text-5xl font-extrabold text-white tracking-tight">
      YouTube Transcriber & Summarizer
    </h1>
    <p class="text-gray-400 text-lg max-w-2xl mx-auto">
      Paste a YouTube link and get a smart summary with timecoded transcript in seconds.
    </p>
    <div class="flex flex-wrap justify-center gap-3 text-xs font-medium mt-6">
      <span class="inline-flex items-center gap-1.5 bg-gray-800/50 text-green-400 px-3 py-1.5 rounded-full border border-gray-700">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        No Signup
      </span>
      <span class="inline-flex items-center gap-1.5 bg-gray-800/50 text-blue-400 px-3 py-1.5 rounded-full border border-gray-700">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        AI Summary
      </span>
      <span class="inline-flex items-center gap-1.5 bg-gray-800/50 text-purple-400 px-3 py-1.5 rounded-full border border-gray-700">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Full Transcript
      </span>
    </div>
  </header>
</template>
