<template>
  <div class="space-y-5">

    <!-- Embedded YouTube Player -->
    <div class="aspect-video bg-black relative rounded-xl overflow-hidden border border-gray-700">
      <iframe
        ref="youtubeIframe"
        class="absolute inset-0 w-full h-full"
        :src="`https://www.youtube.com/embed/${videoId}?enablejsapi=1&rel=0&modestbranding=1`"
        frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen
        title="YouTube video player"
      ></iframe>
    </div>

    <!-- Introduction -->
    <p v-if="summary.introduction" class="text-gray-300 text-sm leading-relaxed">
      {{ summary.introduction }}
    </p>

    <!-- Key Points -->
    <div v-if="summary.key_points?.length" class="space-y-3">
      <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
        <svg class="w-3.5 h-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Key Points
      </h3>
      <div class="space-y-3">
        <div
          v-for="(point, index) in summary.key_points"
          :key="index"
          class="flex flex-col sm:flex-row gap-2 sm:gap-3 group items-start bg-blue-950/20 rounded-lg p-3 border border-blue-800/20"
        >
          <!-- Seek button -->
          <button
            @click="seekTo(point.timecode)"
            class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 text-xs font-mono font-medium text-blue-400 bg-blue-500/10 hover:bg-blue-500/25 rounded border border-blue-500/30 transition-all hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
            :title="'Jump to ' + point.timecode"
          >
            <svg class="w-2.5 h-2.5 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            {{ point.timecode }}
          </button>
          <!-- Point content -->
          <div class="min-w-0">
            <strong class="text-gray-100 text-sm leading-snug block group-hover:text-blue-300 transition-colors">
              {{ point.title }}
            </strong>
            <p class="text-gray-400 text-xs mt-0.5 leading-relaxed">{{ point.details }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Conclusion -->
    <p
      v-if="summary.conclusion"
      class="text-gray-300 text-sm italic leading-relaxed border-t border-gray-700/50 pt-3"
    >
      <span class="font-semibold text-gray-500 not-italic mr-1.5">Conclusion:</span>{{ summary.conclusion }}
    </p>

  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
  videoId: { type: String, required: true },
  /** { introduction: string, key_points: [{timecode, title, details}], conclusion: string|null } */
  summary:  { type: Object, required: true },
});

const youtubeIframe = ref(null);

function seekTo(timecode) {
  if (!youtubeIframe.value || !timecode) return;

  const parts = timecode.split(':').map(Number);
  let seconds = 0;
  if (parts.length === 3) {
    seconds = parts[0] * 3600 + parts[1] * 60 + parts[2];
  } else if (parts.length === 2) {
    seconds = parts[0] * 60 + parts[1];
  }

  youtubeIframe.value.contentWindow.postMessage(
    JSON.stringify({ event: 'command', func: 'seekTo', args: [seconds, true] }),
    'https://www.youtube.com',
  );
}
</script>
