<template>
  <div v-if="task" class="bg-gray-800 rounded-xl p-5 sm:p-6 shadow-lg border border-gray-700 max-w-full overflow-hidden mb-8" aria-live="polite">

    <!-- Status Badge Row -->
    <div class="flex items-center gap-3 mb-5">
      <span :class="statusBadgeClass" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium capitalize" role="status">
        <svg v-if="task.status === 'pending'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <svg v-if="task.status === 'processing'" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-busy="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <svg v-if="task.status === 'completed'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <svg v-if="task.status === 'failed'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ task.status }}
      </span>
      <span v-if="task.status === 'processing'" class="text-gray-400 text-sm">~{{ task.estimated_completion_sec || 90 }}s</span>
    </div>

    <div v-if="task.status === 'pending'" class="text-center py-6"><p class="text-gray-400">Task queued. Starting soon...</p></div>

    <div v-if="task.status === 'processing'" class="py-4">
      <div class="h-1.5 bg-gray-700 rounded-full mb-5 overflow-hidden"><div class="h-full bg-gradient-to-r from-blue-600 to-blue-400 rounded-full transition-all duration-1000" :style="{ width: processingProgress + '%' }"></div></div>
      <div class="space-y-3 max-w-md mx-auto mb-5">
        <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-full"></div>
        <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-3/4"></div>
        <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-1/2"></div>
        <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-5/6"></div>
        <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-2/3"></div>
      </div>
      <p class="text-center text-gray-400 text-sm transition-all duration-500"><span class="mr-1.5">{{ processingStep.icon }}</span>{{ processingStep.text }}</p>
    </div>

    <div v-if="task.status === 'completed' && !task.result" class="py-6 text-center">
      <div class="space-y-3 max-w-md mx-auto mb-4">
        <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-full"></div>
        <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-4/5"></div>
        <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-3/5"></div>
      </div>
      <p class="text-gray-500 text-sm">Loading cached result...</p>
    </div>

    <div v-if="task.status === 'completed' && task.result">

      <!-- Video title / meta -->
      <div class="mb-4">
        <h2 class="text-base font-semibold text-white leading-snug line-clamp-2">{{ task.title || 'YouTube Video' }}</h2>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5">
          <p class="text-xs text-gray-500 truncate max-w-xs">{{ task.youtube_url }}</p>
          <p v-if="task.duration_sec" class="text-xs text-gray-600">{{ formatDuration(task.duration_sec) }}</p>
          <a
            v-if="task.video_id"
            :href="'https://youtube.com/watch?v=' + task.video_id"
            target="_blank" rel="noopener noreferrer"
            class="text-xs text-red-400 hover:text-red-300 transition-colors shrink-0"
          >↗ YouTube</a>
        </div>
      </div>

      <!-- ── Shared YouTube player (above tabs — visible on both Summary and Transcript) ── -->
      <div v-if="task.video_id" class="aspect-video bg-black relative rounded-xl overflow-hidden border border-gray-700 mb-5">
        <iframe
          ref="youtubeIframe"
          class="absolute inset-0 w-full h-full"
          :src="`https://www.youtube.com/embed/${task.video_id}?enablejsapi=1&rel=0&modestbranding=1`"
          frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
          title="YouTube video player"
        ></iframe>
      </div>

      <!-- Tab bar -->
      <div class="flex gap-1 mb-5 bg-gray-700/40 rounded-lg p-1" role="tablist">
        <button @click="activeTab = 'summary'" :class="activeTab === 'summary' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-700/50'" class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-md text-sm font-medium transition-all duration-150" role="tab" :aria-selected="activeTab === 'summary'" aria-controls="panel-summary"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> AI Summary</button>
        <button @click="activeTab = 'transcript'" :class="activeTab === 'transcript' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-700/50'" class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-md text-sm font-medium transition-all duration-150" role="tab" :aria-selected="activeTab === 'transcript'" aria-controls="panel-transcript"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Transcript</button>
        <button v-if="renderedSummary?.resources?.length" @click="activeTab = 'resources'" :class="activeTab === 'resources' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-700/50'" class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-md text-sm font-medium transition-all duration-150" role="tab" :aria-selected="activeTab === 'resources'" aria-controls="panel-resources"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg> Mentioned</button>
      </div>

      <!-- Summary tab -->
      <div v-show="activeTab === 'summary'" id="panel-summary" role="tabpanel">
        <ClickbaitVerdict :verdict="renderedSummary?.clickbait_verdict ?? null" class="mb-5" />
        <SummaryResult
          v-if="renderedSummary && typeof renderedSummary === 'object'"
          :summary="renderedSummary"
          :on-seek="seekToSeconds"
          class="mb-5"
        />
        <p v-else class="text-gray-400 text-sm mb-5">No summary available.</p>
        <button @click="$emit('copySummary')" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-medium px-5 py-3 sm:py-2.5 rounded-lg transition-colors" :aria-label="copySummaryLabel"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> {{ copySummaryLabel }}</button>
      </div>

      <!-- Transcript tab -->
      <div v-show="activeTab === 'transcript'" id="panel-transcript" role="tabpanel">
        <div class="mb-6">
          <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2"><svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Transcript</h2>
            <span class="text-sm text-gray-400">{{ task.result.word_count }} words</span>
          </div>
          <div class="bg-gray-700/50 rounded-lg p-4 max-h-96 overflow-y-auto">
            <div v-for="(chunk, index) in groupedTranscript" :key="index" class="mb-3 last:mb-0 text-sm leading-relaxed text-gray-300 break-words">
              <!-- Seek button — same style as Summary key-point buttons -->
              <button
                v-if="chunk.timeSec !== null"
                @click="seekToSeconds(chunk.timeSec)"
                class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-mono font-medium text-blue-400 bg-blue-500/10 hover:bg-blue-500/25 rounded border border-blue-500/30 transition-all hover:scale-105 active:scale-95 mr-2 shrink-0 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                :title="'Jump to ' + formatTimecode(chunk.timeSec)"
              >
                <svg class="w-2 h-2 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                {{ formatTimecode(chunk.timeSec) }}
              </button>
              <span v-else-if="chunk.timeSec === null && index === 0"></span>{{ chunk.text }}
            </div>
          </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
          <button @click="$emit('copyTranscript')" class="flex-1 inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-medium px-5 py-3 sm:py-2.5 rounded-lg transition-colors" :aria-label="copyTranscriptLabel"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> {{ copyTranscriptLabel }}</button>
          <a :href="'/api/transcribe/' + task.task_id + '/download'" class="flex-1 inline-flex items-center justify-center gap-2 border border-gray-600 hover:border-gray-400 text-gray-300 hover:text-white font-medium px-5 py-3 sm:py-2.5 rounded-lg transition-colors" download aria-label="Download transcript as TXT"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Download .txt</a>
        </div>
      </div>

      <!-- Resources tab -->
      <div v-show="activeTab === 'resources'" id="panel-resources" role="tabpanel">
        <ResourceCatcher :resources="renderedSummary?.resources ?? []" class="mb-5" />
      </div>

    </div>

    <!-- failed state -->
    <div v-if="task.status === 'failed'" class="text-center py-6">
      <svg class="w-14 h-14 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <p class="text-red-300 font-medium mb-2">Transcription Failed</p>
      <p class="text-gray-400 break-words">{{ task.error_message || 'Unknown error occurred.' }}</p>
      <button @click="$emit('retry')" class="mt-6 w-full sm:w-auto bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-medium px-6 py-2.5 rounded-lg transition-colors" aria-label="Retry transcription">Try Again</button>
    </div>
  </div>
</template>
<script setup>
import { ref } from 'vue';
import SummaryResult from './SummaryResult.vue';
import ResourceCatcher from './ResourceCatcher.vue';
import ClickbaitVerdict from './ClickbaitVerdict.vue';
import { formatDuration, formatTimecode } from '../composables/useFormatting.js';

defineProps({
  task: Object, statusBadgeClass: String, processingProgress: Number, processingStep: Object,
  renderedSummary: Object, groupedTranscript: Array,
  copySummaryLabel: String, copyTranscriptLabel: String,
});
const activeTab = defineModel('activeTab', { default: 'summary' });
defineEmits(['copySummary', 'copyTranscript', 'retry']);

/** Shared YouTube iframe ref — one player for both tabs. */
const youtubeIframe = ref(null);

/**
 * Seek the shared YouTube player to @param seconds.
 * Passed as :on-seek to SummaryResult and called directly in transcript timecodes.
 */
function seekToSeconds(seconds) {
  if (!youtubeIframe.value) return;
  youtubeIframe.value.contentWindow.postMessage(
    JSON.stringify({ event: 'command', func: 'seekTo', args: [seconds, true] }),
    'https://www.youtube.com',
  );
}
</script>
