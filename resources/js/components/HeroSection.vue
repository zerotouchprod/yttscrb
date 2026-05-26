<template>
  <header class="text-center space-y-4 mb-10 px-4">
    <h1 class="text-4xl sm:text-5xl font-extrabold text-white tracking-tight">
      YouTube Transcriber & Summarizer
    </h1>
    <p class="text-gray-400 text-lg max-w-2xl mx-auto">
      Paste a YouTube link and get a smart summary with timecoded transcript in seconds.
    </p>
    <div v-if="totalSummarized !== null" class="mt-6">
      <span class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-900/40 to-orange-900/40 text-amber-300 px-4 py-2 rounded-full border border-amber-700/50 text-sm font-semibold">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
        <span>{{ totalSummarized.toLocaleString() }}</span>
        <span class="text-amber-400/70">videos summarized</span>
      </span>
    </div>
    <div class="flex flex-wrap justify-center gap-3 text-xs font-medium mt-4">
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
<script setup>
import { ref, onMounted } from 'vue';

const totalSummarized = ref(null);

onMounted(async () => {
  try {
    const res = await fetch('/api/stats');
    if (res.ok) {
      const data = await res.json();
      totalSummarized.value = data.total_summarized;
    }
  } catch {
    // Silently ignore — counter is non-critical.
  }
});
</script>
