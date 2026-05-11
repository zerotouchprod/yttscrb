<template>
  <nav class="w-full max-w-5xl mx-auto flex items-center justify-between py-5 px-4 sm:px-6 mb-8">
    <a href="/" class="text-2xl font-bold bg-gradient-to-r from-blue-400 via-blue-300 to-blue-200 bg-clip-text text-transparent hover:from-blue-300 hover:via-blue-200 hover:to-blue-100 transition-all duration-300 tracking-tight">
      TubeSum
    </a>
    <div class="relative w-full max-w-xs hidden sm:block">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <input
        :value="searchQuery"
        type="text"
        placeholder="Search summaries..."
        aria-label="Search summaries by title"
        class="w-full bg-gray-800/50 border border-gray-700 text-sm text-white placeholder-gray-500 rounded-full pl-9 pr-4 py-2 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
        :class="{ 'pr-8': searchLoading }"
        :disabled="searchLoading"
        @input="$emit('update:searchQuery', $event.target.value); $emit('searchInput')"
        @keyup.escape="$emit('update:searchQuery', ''); $emit('searchInput')"
      />
      <p v-if="searchQuery.length === 1" class="mt-1 text-xs text-gray-500 text-right">Enter at least 2 characters</p>
      <svg v-if="searchLoading" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>
  </nav>
</template>
<script setup>
defineProps({ searchQuery: String, searchLoading: Boolean });
defineEmits(['update:searchQuery', 'searchInput']);
</script>
