<template>
  <div class="min-h-screen bg-gray-900 text-gray-100">
    <!-- Hero Section -->
    <header class="relative overflow-hidden">
      <div class="hero-glow absolute inset-0 pointer-events-none"></div>
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 text-center relative">
        <h1 class="text-5xl sm:text-6xl font-bold tracking-tight mb-3">
          <span class="bg-gradient-to-r from-blue-400 via-blue-300 to-blue-200 bg-clip-text text-transparent">TubeSum</span>
        </h1>
        <p class="text-lg sm:text-xl text-gray-400 mb-5">
          YouTube Transcriber & Summarizer
        </p>
        <!-- Value Pills -->
        <div class="flex flex-wrap justify-center gap-2">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-800/80 text-gray-300 border border-gray-700/50">
            <svg class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            No Signup
          </span>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-800/80 text-gray-300 border border-gray-700/50">
            <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            AI Summary
          </span>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-800/80 text-gray-300 border border-gray-700/50">
            <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Full Transcript
          </span>
        </div>
      </div>
    </header>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
      <!-- Input Form -->
      <div class="bg-gray-800/80 backdrop-blur-sm rounded-xl p-5 sm:p-6 mb-8 shadow-xl border border-gray-700/50">
        <form @submit.prevent="submitUrl" class="flex flex-col sm:flex-row gap-3">
          <div class="flex-1 min-w-0">
            <input
              v-model="youtubeUrl"
              type="text"
              placeholder="https://youtube.com/watch?v=..."
              aria-label="YouTube video URL"
              class="w-full bg-gray-700/80 border border-gray-600 rounded-lg px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all duration-200"
              :class="{ 'border-red-500 focus:ring-red-500/50 focus:border-red-500': urlValidationError }"
              :disabled="isLoading"
              @input="urlValidationError = null"
            />
            <p v-if="urlValidationError" class="mt-1.5 text-sm text-red-400" role="alert">{{ urlValidationError }}</p>
          </div>
          <button
            type="submit"
            :disabled="isLoading || !youtubeUrl.trim()"
            class="w-full sm:w-auto bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 disabled:from-gray-600 disabled:to-gray-600 disabled:cursor-not-allowed text-white font-medium px-6 py-3 rounded-lg transition-all duration-200 shadow-lg shadow-blue-500/20 disabled:shadow-none whitespace-nowrap"
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

      <!-- Error -->
      <div v-if="error" class="bg-red-900/50 border border-red-700 rounded-xl p-4 mb-8 max-w-full overflow-hidden" role="alert">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <p class="text-red-300 break-words">{{ error }}</p>
        </div>
      </div>

      <!-- Status Card -->
      <div v-if="task" class="bg-gray-800 rounded-xl p-5 sm:p-6 shadow-lg border border-gray-700 max-w-full overflow-hidden" aria-live="polite">
        <!-- Status Badge Row -->
        <div class="flex items-center gap-3 mb-5">
          <span :class="statusBadgeClass" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium capitalize" role="status">
            <!-- Pending: clock icon -->
            <svg v-if="task.status === 'pending'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <!-- Processing: spinner -->
            <svg v-if="task.status === 'processing'" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-busy="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <!-- Completed: check-circle -->
            <svg v-if="task.status === 'completed'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <!-- Failed: x-circle -->
            <svg v-if="task.status === 'failed'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ task.status }}
          </span>
          <span v-if="task.status === 'processing'" class="text-gray-400 text-sm flex items-center gap-1">
            <svg class="animate-spin h-4 w-4 inline" viewBox="0 0 24 24" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            Estimated: ~{{ task.estimated_completion_sec || 90 }}s
          </span>
        </div>

        <!-- Pending State -->
        <div v-if="task.status === 'pending'" class="text-center py-6">
          <p class="text-gray-400">Task queued. Starting soon...</p>
        </div>

        <!-- Processing State -->
        <div v-if="task.status === 'processing'" class="text-center py-6">
          <div class="space-y-3 max-w-md mx-auto">
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-full"></div>
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-3/4"></div>
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-1/2"></div>
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-5/6"></div>
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-2/3"></div>
          </div>
          <p class="text-gray-400 mt-6">Transcribing your video... This may take a minute or two.</p>
        </div>

        <!-- Completed State -->
        <div v-if="task.status === 'completed' && task.result">
          <!-- Tab Switcher -->
          <div class="flex gap-1 mb-5 bg-gray-700/40 rounded-lg p-1" role="tablist">
            <button
              @click="activeTab = 'summary'"
              :class="activeTab === 'summary'
                ? 'bg-blue-600 text-white shadow-md'
                : 'text-gray-400 hover:text-gray-200 hover:bg-gray-700/50'"
              class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-md text-sm font-medium transition-all duration-150"
              role="tab"
              :aria-selected="activeTab === 'summary'"
              aria-controls="panel-summary"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
              AI Summary
            </button>
            <button
              @click="activeTab = 'transcript'"
              :class="activeTab === 'transcript'
                ? 'bg-blue-600 text-white shadow-md'
                : 'text-gray-400 hover:text-gray-200 hover:bg-gray-700/50'"
              class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-md text-sm font-medium transition-all duration-150"
              role="tab"
              :aria-selected="activeTab === 'transcript'"
              aria-controls="panel-transcript"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              Transcript
            </button>
          </div>

          <!-- Summary Panel -->
          <div v-show="activeTab === 'summary'" id="panel-summary" role="tabpanel">
            <div class="mb-6 bg-gray-700/60 border-l-4 border-blue-500 rounded-r-lg p-4">
              <h2 class="text-lg font-semibold text-white mb-2 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Summary
              </h2>
              <p class="text-gray-300 break-words">{{ task.result.summary }}</p>
            </div>
            <button
              @click="copySummary"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-medium px-5 py-3 sm:py-2.5 rounded-lg transition-colors"
              :aria-label="copySummaryLabel"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              {{ copySummaryLabel }}
            </button>
          </div>

          <!-- Transcript Panel -->
          <div v-show="activeTab === 'transcript'" id="panel-transcript" role="tabpanel">
            <div class="mb-6">
              <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                  <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  Transcript
                </h2>
                <span class="text-sm text-gray-400">{{ task.result.word_count }} words</span>
              </div>
              <div class="bg-gray-700/50 rounded-lg p-4 max-h-96 overflow-y-auto">
                <p class="text-gray-300 whitespace-pre-wrap text-sm leading-relaxed break-words">{{ task.result.transcript }}</p>
              </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
              <button
                @click="copyTranscript"
                class="flex-1 inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-medium px-5 py-3 sm:py-2.5 rounded-lg transition-colors"
                :aria-label="copyTranscriptLabel"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                {{ copyTranscriptLabel }}
              </button>
              <a
                :href="`/api/transcribe/${task.task_id}/download`"
                class="flex-1 inline-flex items-center justify-center gap-2 border border-gray-600 hover:border-gray-400 text-gray-300 hover:text-white font-medium px-5 py-3 sm:py-2.5 rounded-lg transition-colors"
                download
                aria-label="Download transcript as TXT"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Download .txt
              </a>
            </div>
          </div>
        </div>

        <!-- Failed State -->
        <div v-if="task.status === 'failed'" class="text-center py-6">
          <svg class="w-14 h-14 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <p class="text-red-300 font-medium mb-2">Transcription Failed</p>
          <p class="text-gray-400 break-words">{{ task.error_message || 'Unknown error occurred.' }}</p>
          <button
            @click="retry"
            class="mt-6 w-full sm:w-auto bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-medium px-6 py-2.5 rounded-lg transition-colors"
            aria-label="Retry transcription"
          >
            Try Again
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue';
import axios from 'axios';

const youtubeUrl = ref('');
const task = ref(null);
const error = ref(null);
const isLoading = ref(false);
const urlValidationError = ref(null);
const copySummaryLabel = ref('Copy Summary');
const copyTranscriptLabel = ref('Copy Transcript');
const activeTab = ref('summary');
let pollTimer = null;

const statusBadgeClass = computed(() => {
  const map = {
    pending: 'bg-yellow-600/30 text-yellow-200 border border-yellow-500/30',
    processing: 'bg-blue-600/30 text-blue-200 border border-blue-500/30',
    completed: 'bg-green-600/30 text-green-200 border border-green-500/30',
    failed: 'bg-red-600/30 text-red-200 border border-red-500/30',
  };
  return map[task.value?.status] || 'bg-gray-600 text-gray-100';
});

function isValidYouTubeUrl(url) {
  const patterns = [
    /^https?:\/\/(www\.)?youtube\.com\/watch\?v=[\w-]+/,
    /^https?:\/\/(www\.)?youtu\.be\/[\w-]+/,
    /^https?:\/\/(m\.)?youtube\.com\/watch\?v=[\w-]+/,
  ];
  return patterns.some(p => p.test(url));
}

async function submitUrl() {
  error.value = null;
  urlValidationError.value = null;

  if (!isValidYouTubeUrl(youtubeUrl.value.trim())) {
    urlValidationError.value = 'Please enter a valid YouTube URL (e.g. https://youtube.com/watch?v=... or https://youtu.be/...)';
    return;
  }

  isLoading.value = true;

  try {
    const { data } = await axios.post('/api/transcribe', {
      youtube_url: youtubeUrl.value,
    });
    task.value = data;
    activeTab.value = 'summary';
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

async function copyTranscript() {
  if (!task.value?.result?.transcript) return;
  try {
    await navigator.clipboard.writeText(task.value.result.transcript);
    copyTranscriptLabel.value = 'Copied!';
    setTimeout(() => { copyTranscriptLabel.value = 'Copy Transcript'; }, 2000);
  } catch {
    copyTranscriptLabel.value = 'Failed';
    setTimeout(() => { copyTranscriptLabel.value = 'Copy Transcript'; }, 2000);
  }
}

async function copySummary() {
  if (!task.value?.result?.summary) return;
  try {
    await navigator.clipboard.writeText(task.value.result.summary);
    copySummaryLabel.value = 'Copied!';
    setTimeout(() => { copySummaryLabel.value = 'Copy Summary'; }, 2000);
  } catch {
    copySummaryLabel.value = 'Failed';
    setTimeout(() => { copySummaryLabel.value = 'Copy Summary'; }, 2000);
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
