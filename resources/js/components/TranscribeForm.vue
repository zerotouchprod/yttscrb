<template>
  <div class="bg-gray-800/80 backdrop-blur-sm rounded-xl p-5 sm:p-6 mb-8 shadow-xl border border-gray-700/50 transition-all duration-200 focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500">
    <form @submit.prevent="$emit('submit')" class="flex flex-col sm:flex-row gap-3">
      <div class="flex-1 min-w-0">
        <input
          :value="youtubeUrl"
          type="text"
          placeholder="https://youtube.com/watch?v=..."
          aria-label="YouTube video URL"
          class="w-full bg-gray-700/80 border border-gray-600 rounded-lg px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-0 transition-all duration-200"
          :class="{ 'border-red-500 ring-2 ring-red-500': urlValidationError }"
          :disabled="isLoading"
          @input="$emit('update:youtubeUrl', $event.target.value); $emit('clearError')"
        />
        <p v-if="urlValidationError" class="mt-1.5 text-sm text-red-400" role="alert">{{ urlValidationError }}</p>
      </div>
      <button
        type="submit"
        :disabled="isLoading"
        class="w-full sm:w-auto bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 disabled:opacity-60 disabled:cursor-not-allowed text-white font-medium px-6 py-3 rounded-lg transition-all duration-200 shadow-lg shadow-blue-500/20 disabled:shadow-none whitespace-nowrap"
      >
        <span v-if="isLoading" class="flex items-center justify-center gap-2">
          <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          Processing
        </span>
        <span v-else>Transcribe</span>
      </button>
    </form>
  </div>
</template>
<script setup>
defineProps({ youtubeUrl: String, isLoading: Boolean, urlValidationError: String });
defineEmits(['submit', 'update:youtubeUrl', 'clearError']);
</script>
