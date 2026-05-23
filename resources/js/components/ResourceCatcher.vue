<template>
  <div v-if="resources.length" class="space-y-3">
    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
      <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
      </svg>
      Mentioned in this Video
    </h3>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
      <a
        v-for="(item, index) in resources"
        :key="index"
        :href="resourceLink(item)"
        target="_blank"
        rel="noopener noreferrer"
        class="flex items-center p-3 transition-colors border border-gray-700 rounded-lg bg-gray-800/50 hover:bg-gray-700 hover:border-blue-500 group"
      >
        <!-- Category icon -->
        <div class="flex items-center justify-center w-10 h-10 mr-4 rounded-md bg-gray-900 group-hover:bg-gray-800 transition-colors"
             :class="iconColorClass(item.type)">
          <!-- tool icon -->
          <svg v-if="item.type === 'tool'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.08 5.09a1.41 1.41 0 01-2-2l5.09-5.08m1.99-1.99l4.24-4.24m-9.9-4.24a4.002 4.002 0 015.66 5.66l-5.66-5.66zm10.97 2.47l-4.24 4.24m1.41-5.65l-5.65 5.65"/>
          </svg>
          <!-- person icon -->
          <svg v-else-if="item.type === 'person'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
          </svg>
          <!-- book icon -->
          <svg v-else-if="item.type === 'book'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
          </svg>
          <!-- service icon -->
          <svg v-else-if="item.type === 'service'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/>
          </svg>
          <!-- link / default icon -->
          <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
          </svg>
        </div>

        <div class="min-w-0 flex-1">
          <h4 class="font-medium text-gray-200 group-hover:text-white transition-colors truncate">
            {{ item.name }}
          </h4>
          <p class="text-xs text-gray-500 capitalize">
            {{ item.type }}
          </p>
        </div>

        <!-- External link / search indicator -->
        <svg class="w-4 h-4 ml-auto text-gray-600 opacity-0 group-hover:opacity-100 transition-opacity shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
        </svg>
      </a>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  resources: { type: Array, required: true },
})

/**
 * Smart link fallback:
 * - If LLM returned an exact URL → use it directly.
 * - If URL is null → fall back to a Google search for the resource name.
 * This gives users a useful click target even when the speaker didn't dictate a full URL.
 */
function resourceLink(item) {
  if (item.url) {
    return item.url
  }
  return `https://www.google.com/search?q=${encodeURIComponent(item.name)}`
}

/**
 * Color mapping per resource type — matches icon container styling.
 */
function iconColorClass(type) {
  switch (type) {
    case 'tool':    return 'text-blue-400'
    case 'person':  return 'text-pink-400'
    case 'book':    return 'text-amber-400'
    case 'service': return 'text-purple-400'
    case 'link':    return 'text-cyan-400'
    default:        return 'text-gray-400'
  }
}
</script>
