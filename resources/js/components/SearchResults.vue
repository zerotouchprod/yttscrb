<template>
  <div v-if="searchResults !== null" class="mb-6">
    <div v-if="searchError" class="bg-red-900/50 border border-red-700 rounded-xl p-4 mb-4" role="alert">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-red-300 break-words">{{ searchError }}</p>
      </div>
    </div>
    <div v-if="searchLoading && searchResults.length === 0" class="space-y-3">
      <div v-for="n in 3" :key="n" class="animate-shimmer h-20 rounded-xl bg-gradient-to-r from-gray-800 via-gray-700 to-gray-800 bg-[length:200%_100%]"></div>
    </div>
    <div v-if="!searchLoading && searchQuery.length >= 2 && searchResults.length === 0 && !searchError" class="text-center py-8">
      <svg class="w-12 h-12 text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <p class="text-gray-500">No transcripts found matching your query.</p>
    </div>
    <div v-if="searchResults.length > 0" class="space-y-3">
      <div v-for="item in searchResults" :key="item.task_id" class="bg-gray-800/70 rounded-lg p-4 border border-gray-700/40 hover:border-gray-600/60 transition-colors">
        <div class="min-w-0 flex-1">
          <a v-if="item._links?.public_page" :href="item._links.public_page" class="text-sm font-semibold text-blue-400 hover:text-blue-300 line-clamp-2 mb-1 block">{{ item.title || 'Untitled' }}</a>
          <span v-else class="text-sm font-semibold text-gray-300 line-clamp-2 mb-1 block">{{ item.title || 'Untitled' }}</span>
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
            <span v-if="item.duration_sec">{{ formatDuration(item.duration_sec) }}</span>
            <span v-if="item.completed_at">{{ formatDate(item.completed_at) }}</span>
            <a :href="item.youtube_url" target="_blank" rel="noopener noreferrer" class="text-blue-500 hover:text-blue-400 truncate">YouTube</a>
          </div>
        </div>
      </div>
      <div v-if="searchHasMore" class="text-center pt-2">
        <button @click="$emit('loadMore')" :disabled="searchLoading" class="bg-gray-700 hover:bg-gray-600 disabled:opacity-50 text-gray-200 font-medium px-6 py-2.5 rounded-lg transition-colors text-sm">Load More</button>
      </div>
    </div>
  </div>
</template>
<script setup>
import { formatDuration, formatDate } from '../composables/useFormatting.js';
defineProps({ searchResults: Array, searchLoading: Boolean, searchError: String, searchHasMore: Boolean, searchQuery: String });
defineEmits(['loadMore']);
</script>
