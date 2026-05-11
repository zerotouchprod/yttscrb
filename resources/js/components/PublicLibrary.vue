<template>
  <section v-if="!hideWhenTask && recentTasks.length > 0" class="mt-16">
    <h2 class="text-lg font-semibold text-white flex items-center gap-2 mb-3">
      <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
      Public Library
    </h2>
    <div class="relative">
      <div class="flex gap-3 overflow-x-auto pb-2 -mx-1 px-1 scrollbar-hide" style="-webkit-mask-image: linear-gradient(to right, black 85%, transparent 100%); mask-image: linear-gradient(to right, black 85%, transparent 100%);">
        <div v-for="t in recentTasks" :key="t.task_id"
          class="flex-shrink-0 w-64 bg-gray-800/70 rounded-lg border border-gray-700/40 hover:border-gray-600 transition-all duration-300 overflow-hidden hover:-translate-y-1 hover:shadow-lg hover:shadow-blue-500/10">
          <div class="aspect-video bg-gray-800 flex items-center justify-center relative overflow-hidden">
            <svg class="w-8 h-8 text-gray-600 absolute" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <img v-if="t.video_id" :src="'https://img.youtube.com/vi/' + t.video_id + '/mqdefault.jpg'" :alt="t.title || 'Video thumbnail'" class="w-full h-full object-cover relative z-10" loading="lazy" @error="e => e.target.style.display = 'none'" />
          </div>
          <div class="p-3">
            <a v-if="t._links?.public_page" :href="t._links.public_page" class="text-sm font-medium text-gray-300 hover:text-blue-400 line-clamp-2 mb-1.5 block transition-colors">{{ t.title || 'Untitled' }}</a>
            <span v-else class="text-sm font-medium text-gray-400 line-clamp-2 mb-1.5 block">{{ t.title || 'Untitled' }}</span>
            <div class="flex items-center gap-2 text-xs text-gray-500">
              <span v-if="t.duration_sec">{{ formatDuration(t.duration_sec) }}</span>
              <span v-if="t.completed_at" class="truncate">{{ formatDate(t.completed_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-3 text-center">
      <a href="/history" class="inline-flex items-center gap-1 text-sm text-blue-400 hover:text-blue-300 transition-colors">
        Browse all summaries
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </a>
    </div>
  </section>
</template>
<script setup>
import { formatDuration, formatDate } from '../composables/useFormatting.js';
defineProps({ recentTasks: Array, hideWhenTask: Boolean });
</script>
