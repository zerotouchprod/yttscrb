<template>
  <div v-if="meta" class="flex flex-wrap items-center gap-2">
    <!-- Complexity badge -->
    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full"
          :class="complexityClass(meta.complexity)">
      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
      </svg>
      {{ capitalize(meta.complexity) }}
    </span>

    <!-- Reading time -->
    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-400 bg-gray-800 rounded-full">
      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      {{ meta.reading_time_minutes }} min read
    </span>

    <!-- Jargon density -->
    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-400 bg-gray-800 rounded-full">
      Jargon: {{ capitalize(meta.jargon_density) }}
    </span>

    <!-- Target audience tooltip -->
    <span
      class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-400 bg-gray-800 rounded-full cursor-help"
      :title="meta.target_audience"
    >
      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
      </svg>
      {{ truncate(meta.target_audience, 40) }}
    </span>
  </div>
</template>

<script setup>
defineProps({
  meta: { type: Object, default: null },
})

function capitalize(str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}

function truncate(str, max) {
  if (!str) return ''
  return str.length > max ? str.slice(0, max) + '…' : str
}

function complexityClass(complexity) {
  switch (complexity) {
    case 'beginner':     return 'text-green-400 bg-green-500/10'
    case 'intermediate': return 'text-amber-400 bg-amber-500/10'
    case 'advanced':     return 'text-orange-400 bg-orange-500/10'
    case 'expert':       return 'text-red-400 bg-red-500/10'
    default:             return 'text-gray-400 bg-gray-500/10'
  }
}
</script>
