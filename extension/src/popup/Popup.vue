<template>
  <div class="p-4 flex flex-col gap-4">
    <!-- Header -->
    <div class="flex items-center gap-2">
      <span class="text-xl font-bold bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">
        ✨ TubeSum
      </span>
      <span class="text-xs text-gray-500 ml-auto">v1.0.0</span>
    </div>

    <!-- State: Not YouTube -->
    <div v-if="state === 'not-youtube'" class="flex flex-col items-center gap-3 py-8 text-center">
      <div class="text-5xl">🎬</div>
      <p class="text-gray-400 text-sm">Open a YouTube video to use TubeSum.</p>
    </div>

    <!-- State: Idle (on YouTube, ready to summarize) -->
    <div v-if="state === 'idle'" class="flex flex-col items-center gap-4 py-6">
      <p class="text-gray-300 text-sm text-center">
        Get AI-powered summary of this video — key points, clickbait detection, and more.
      </p>
      <button
        @click="summarize()"
        class="w-full py-2.5 px-4 bg-gradient-to-r from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 text-white font-medium rounded-xl transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
      >
        ✨ Summarize This Video
      </button>
    </div>

    <!-- State: Loading -->
    <div v-if="state === 'loading'" class="flex flex-col items-center gap-4 py-6">
      <!-- Spinner -->
      <div class="relative w-12 h-12">
        <div class="absolute inset-0 rounded-full border-2 border-gray-700"></div>
        <div class="absolute inset-0 rounded-full border-2 border-t-indigo-400 border-r-transparent border-b-transparent border-l-transparent animate-spin"></div>
      </div>
      <p class="text-gray-400 text-sm">AI is analyzing the video...</p>
      <p class="text-gray-600 text-xs">This usually takes 30–90 seconds</p>
    </div>

    <!-- State: Error -->
    <div v-if="state === 'error'" class="flex flex-col gap-3">
      <div class="bg-red-900/40 border border-red-700/50 rounded-xl p-3">
        <p class="text-red-300 text-sm">{{ errorMessage }}</p>
      </div>
      <button
        @click="reset()"
        class="w-full py-2 px-4 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-xl transition-colors"
      >
        Try Again
      </button>
    </div>

    <!-- State: Success -->
    <div v-if="state === 'success' && summary" class="flex flex-col gap-3">
      <!-- Video Title -->
      <p v-if="videoTitle" class="text-sm font-medium text-gray-200 line-clamp-2">
        {{ videoTitle }}
      </p>

      <!-- Clickbait Verdict -->
      <div
        v-if="summary.clickbait_verdict"
        class="flex items-center gap-2"
      >
        <span
          class="px-2.5 py-0.5 rounded-full text-xs font-semibold"
          :class="clickbaitBadgeClass"
        >
          {{ clickbaitLabel }}
        </span>
        <span class="text-xs text-gray-400">{{ summary.clickbait_verdict.comment }}</span>
      </div>

      <!-- Key Points -->
      <div class="flex flex-col gap-2">
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Key Points</h3>
        <ul class="flex flex-col gap-2">
          <li
            v-for="(point, idx) in summary.key_points.slice(0, 5)"
            :key="idx"
            class="bg-gray-800/60 rounded-lg p-2.5 text-sm"
          >
            <span class="text-indigo-400 font-mono text-xs">{{ point.timecode }}</span>
            <span class="text-gray-200 font-medium ml-2">{{ point.title }}</span>
            <p class="text-gray-400 text-xs mt-1 line-clamp-2">{{ point.details }}</p>
          </li>
        </ul>
      </div>

      <!-- CTA: View Full -->
      <a
        v-if="publicPageUrl"
        :href="publicPageUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="w-full py-2.5 px-4 bg-indigo-500 hover:bg-indigo-600 text-white font-medium text-sm rounded-xl text-center transition-colors"
      >
        Open Full Transcript →
      </a>

      <!-- Summarize Again -->
      <button
        @click="reset()"
        class="w-full py-2 px-4 bg-gray-800 hover:bg-gray-700 text-gray-400 text-xs rounded-xl transition-colors"
      >
        Summarize Another Video
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useTranscription } from '../composables/useTranscription';

const {
  state,
  errorMessage,
  summary,
  publicPageUrl,
  videoTitle,
  initialize,
  summarize,
  reset,
} = useTranscription();

onMounted(() => {
  initialize();
});

const clickbaitScore = computed(() => summary.value?.clickbait_verdict?.score ?? 0);

const clickbaitBadgeClass = computed(() => {
  if (clickbaitScore.value >= 70) {
    return 'bg-red-900/60 text-red-300 border border-red-700/50';
  }
  if (clickbaitScore.value >= 40) {
    return 'bg-yellow-900/60 text-yellow-300 border border-yellow-700/50';
  }
  return 'bg-green-900/60 text-green-300 border border-green-700/50';
});

const clickbaitLabel = computed(() => {
  if (clickbaitScore.value >= 70) return '⚠️ Clickbait';
  if (clickbaitScore.value >= 40) return '🤔 Mixed';
  return '✅ Accurate';
});
</script>
