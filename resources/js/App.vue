<template>
  <div class="min-h-screen bg-gray-900 text-gray-100">
    <div class="max-w-3xl mx-auto px-4 py-12">
      <!-- Header -->
      <header class="text-center mb-12">
        <h1 class="text-4xl font-bold text-white mb-2">YTTSCRB</h1>
        <p class="text-gray-400 text-lg">YouTube Transcriber & Summarizer</p>
      </header>

      <!-- Input Form -->
      <div class="bg-gray-800 rounded-xl p-6 mb-8 shadow-lg border border-gray-700">
        <form @submit.prevent="submitUrl" class="flex gap-3">
          <input
            v-model="youtubeUrl"
            type="text"
            placeholder="https://youtube.com/watch?v=..."
            class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            :disabled="isLoading"
          />
          <button
            type="submit"
            :disabled="isLoading || !youtubeUrl.trim()"
            class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white font-medium px-6 py-3 rounded-lg transition-colors"
          >
            <span v-if="isLoading" class="flex items-center gap-2">
              <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              Processing
            </span>
            <span v-else>Transcribe</span>
          </button>
        </form>
      </div>

      <!-- Error -->
      <div v-if="error" class="bg-red-900/50 border border-red-700 rounded-xl p-4 mb-8">
        <p class="text-red-300">{{ error }}</p>
      </div>

      <!-- Status Card -->
      <div v-if="task" class="bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-700">
        <!-- Status Badge -->
        <div class="flex items-center gap-3 mb-4">
          <span
            :class="statusBadgeClass"
            class="px-3 py-1 rounded-full text-sm font-medium capitalize"
          >
            {{ task.status }}
          </span>
          <span v-if="task.status === 'processing'" class="text-gray-400 text-sm">
            <svg class="animate-spin h-4 w-4 inline mr-1" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            Estimated: ~{{ task.estimated_completion_sec || 90 }}s
          </span>
        </div>

        <!-- Pending State -->
        <div v-if="task.status === 'pending'" class="text-center py-8">
          <p class="text-gray-400">Task queued. Starting soon...</p>
        </div>

        <!-- Processing State -->
        <div v-if="task.status === 'processing'" class="text-center py-8">
          <div class="animate-pulse">
            <div class="h-3 bg-gray-700 rounded w-3/4 mx-auto mb-3"></div>
            <div class="h-3 bg-gray-700 rounded w-1/2 mx-auto mb-3"></div>
            <div class="h-3 bg-gray-700 rounded w-2/3 mx-auto"></div>
          </div>
          <p class="text-gray-400 mt-6">Transcribing your video... This may take a minute or two.</p>
        </div>

        <!-- Completed State -->
        <div v-if="task.status === 'completed' && task.result">
          <!-- Summary -->
          <div class="mb-6">
            <h2 class="text-lg font-semibold text-white mb-2">Summary</h2>
            <p class="text-gray-300 bg-gray-700/50 rounded-lg p-4">{{ task.result.summary }}</p>
          </div>

          <!-- Transcript -->
          <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
              <h2 class="text-lg font-semibold text-white">Transcript</h2>
              <span class="text-sm text-gray-400">{{ task.result.word_count }} words</span>
            </div>
            <div class="bg-gray-700/50 rounded-lg p-4 max-h-96 overflow-y-auto">
              <p class="text-gray-300 whitespace-pre-wrap text-sm leading-relaxed">{{ task.result.transcript }}</p>
            </div>
          </div>

          <!-- Download Button -->
          <a
            :href="`/api/transcribe/${task.task_id}/download`"
            class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-medium px-4 py-2 rounded-lg transition-colors"
            download
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Download TXT
          </a>
        </div>

        <!-- Failed State -->
        <div v-if="task.status === 'failed'" class="text-center py-8">
          <div class="text-red-400 text-5xl mb-4">!</div>
          <p class="text-red-300 font-medium mb-2">Transcription Failed</p>
          <p class="text-gray-400">{{ task.error_message || 'Unknown error occurred.' }}</p>
          <button
            @click="retry"
            class="mt-6 bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded-lg transition-colors"
          >
            Try Again
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import axios from 'axios';

const youtubeUrl = ref('');
const task = ref(null);
const error = ref(null);
const isLoading = ref(false);
let pollTimer = null;

const statusBadgeClass = computed(() => {
  const map = {
    pending: 'bg-yellow-600 text-yellow-100',
    processing: 'bg-blue-600 text-blue-100',
    completed: 'bg-green-600 text-green-100',
    failed: 'bg-red-600 text-red-100',
  };
  return map[task.value?.status] || 'bg-gray-600 text-gray-100';
});

async function submitUrl() {
  error.value = null;
  isLoading.value = true;

  try {
    const { data } = await axios.post('/api/transcribe', {
      youtube_url: youtubeUrl.value,
    });
    task.value = data;
    startPolling(data.task_id);
  } catch (e) {
    error.value = e.response?.data?.error?.message || 'Failed to submit URL. Please try again.';
  } finally {
    isLoading.value = false;
  }
}

function startPolling(taskId) {
  stopPolling();
  let attempts = 0;

  pollTimer = setInterval(async () => {
    attempts++;
    try {
      const { data } = await axios.get(`/api/transcribe/${taskId}`);
      task.value = data;

      if (data.status === 'completed' || data.status === 'failed') {
        stopPolling();
      }

      // Stop polling after 120 attempts (~10 minutes)
      if (attempts > 120) {
        stopPolling();
      }
    } catch (e) {
      error.value = 'Failed to fetch task status.';
      stopPolling();
    }
  }, 5000);
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

function retry() {
  task.value = null;
  error.value = null;
  submitUrl();
}

onUnmounted(() => {
  stopPolling();
});
</script>
