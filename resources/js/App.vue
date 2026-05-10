<template>
  <div class="min-h-screen bg-gray-900 text-gray-100">
    <!-- Hero Section -->
    <header class="relative overflow-hidden">
      <div class="hero-glow absolute inset-0 pointer-events-none"></div>
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 text-center relative">
        <h1 class="text-5xl sm:text-6xl font-bold tracking-tight mb-3">
          <a href="/" class="bg-gradient-to-r from-blue-400 via-blue-300 to-blue-200 bg-clip-text text-transparent hover:from-blue-300 hover:via-blue-200 hover:to-blue-100 transition-all duration-300">TubeSum</a>
        </h1>
        <p class="text-lg sm:text-xl text-gray-400 mb-5">
          YouTube Transcriber & Summarizer
        </p>
        <!-- Value Pills — informational badges, not buttons -->
        <div class="flex flex-wrap justify-center gap-2">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-700/30 text-gray-500">
            <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            No Signup
          </span>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-700/30 text-gray-500">
            <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            AI Summary
          </span>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-700/30 text-gray-500">
            <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Full Transcript
          </span>
        </div>
      </div>
    </header>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
      <!-- Recently Transcribed -->
      <section v-if="recentTasks.length > 0" class="mb-8">
        <h2 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
          <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Recently Transcribed
        </h2>
        <div class="flex gap-3 overflow-x-auto pb-2 -mx-1 px-1 scrollbar-thin">
          <div
            v-for="t in recentTasks"
            :key="t.task_id"
            class="flex-shrink-0 w-56 bg-gray-800/70 rounded-lg p-3 border border-gray-700/40 hover:border-gray-600/60 transition-colors"
          >
            <a
              v-if="t._links?.public_page"
              :href="t._links.public_page"
              class="text-sm font-medium text-blue-400 hover:text-blue-300 line-clamp-2 mb-1.5 block"
            >{{ t.title || 'Untitled' }}</a>
            <span v-else class="text-sm font-medium text-gray-400 line-clamp-2 mb-1.5 block">{{ t.title || 'Untitled' }}</span>
            <div class="flex items-center gap-2 text-xs text-gray-500">
              <span v-if="t.duration_sec">{{ formatDuration(t.duration_sec) }}</span>
              <span v-if="t.completed_at" class="truncate">{{ formatDate(t.completed_at) }}</span>
            </div>
          </div>
        </div>
        <div class="mt-3 text-center">
          <a
            href="/history"
            class="inline-flex items-center gap-1 text-sm text-blue-400 hover:text-blue-300 transition-colors"
          >
            View all history
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </a>
        </div>
      </section>

      <!-- Input Form -->
      <div class="bg-gray-800/80 backdrop-blur-sm rounded-xl p-5 sm:p-6 mb-6 shadow-xl border border-gray-700/50">
        <form @submit.prevent="submitUrl" class="flex flex-col sm:flex-row gap-3">
          <div class="flex-1 min-w-0">
            <input
              v-model="youtubeUrl"
              type="text"
              placeholder="https://youtube.com/watch?v=..."
              aria-label="YouTube video URL"
              class="w-full bg-gray-700/80 border border-gray-600 rounded-lg px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
              :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': urlValidationError }"
              :disabled="isLoading"
              @input="urlValidationError = null"
            />
            <p v-if="urlValidationError" class="mt-1.5 text-sm text-red-400" role="alert">{{ urlValidationError }}</p>
          </div>
          <!-- CTA: always blue, never gray — validation happens on click -->
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

      <!-- Search Section -->
      <div class="bg-gray-800/80 backdrop-blur-sm rounded-xl p-5 sm:p-6 mb-6 shadow-xl border border-gray-700/50">
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search transcripts by title..."
            aria-label="Search transcripts by title"
            class="w-full bg-gray-700/80 border border-gray-600 rounded-lg pl-10 pr-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
            :disabled="searchLoading"
            @input="onSearchInput"
          />
          <svg v-if="searchLoading" class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        </div>
        <p v-if="searchQuery.length === 1" class="mt-2 text-xs text-gray-500">Enter at least 2 characters to search.</p>
      </div>

      <!-- Search Results -->
      <div v-if="searchResults !== null" class="mb-6">
        <!-- Search Error -->
        <div v-if="searchError" class="bg-red-900/50 border border-red-700 rounded-xl p-4 mb-4" role="alert">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-red-300 break-words">{{ searchError }}</p>
          </div>
        </div>

        <!-- Search Loading Skeleton -->
        <div v-if="searchLoading && searchResults.length === 0" class="space-y-3">
          <div v-for="n in 3" :key="n" class="animate-shimmer h-20 rounded-xl bg-gradient-to-r from-gray-800 via-gray-700 to-gray-800 bg-[length:200%_100%]"></div>
        </div>

        <!-- Empty Results -->
        <div v-if="!searchLoading && searchQuery.length >= 2 && searchResults.length === 0 && !searchError" class="text-center py-8">
          <svg class="w-12 h-12 text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <p class="text-gray-500">No transcripts found matching your query.</p>
        </div>

        <!-- Result Cards -->
        <div v-if="searchResults.length > 0" class="space-y-3">
          <div
            v-for="item in searchResults"
            :key="item.task_id"
            class="bg-gray-800/70 rounded-lg p-4 border border-gray-700/40 hover:border-gray-600/60 transition-colors"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <a
                  v-if="item._links?.public_page"
                  :href="item._links.public_page"
                  class="text-sm font-semibold text-blue-400 hover:text-blue-300 line-clamp-2 mb-1 block"
                >{{ item.title || 'Untitled' }}</a>
                <span v-else class="text-sm font-semibold text-gray-300 line-clamp-2 mb-1 block">{{ item.title || 'Untitled' }}</span>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                  <span v-if="item.duration_sec">{{ formatDuration(item.duration_sec) }}</span>
                  <span v-if="item.completed_at">{{ formatDate(item.completed_at) }}</span>
                  <a
                    :href="item.youtube_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-blue-500 hover:text-blue-400 truncate"
                  >YouTube</a>
                </div>
              </div>
            </div>
          </div>

          <!-- Load More -->
          <div v-if="searchHasMore" class="text-center pt-2">
            <button
              @click="searchLoadMore"
              :disabled="searchLoading"
              class="bg-gray-700 hover:bg-gray-600 disabled:opacity-50 text-gray-200 font-medium px-6 py-2.5 rounded-lg transition-colors text-sm"
            >
              Load More
            </button>
          </div>
        </div>
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
            <svg v-if="task.status === 'pending'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <svg v-if="task.status === 'processing'" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-busy="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <svg v-if="task.status === 'completed'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <svg v-if="task.status === 'failed'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ task.status }}
          </span>
          <span v-if="task.status === 'processing'" class="text-gray-400 text-sm">
            ~{{ task.estimated_completion_sec || 90 }}s
          </span>
        </div>

        <!-- Pending State -->
        <div v-if="task.status === 'pending'" class="text-center py-6">
          <p class="text-gray-400">Task queued. Starting soon...</p>
        </div>

        <!-- Processing State — dynamic step labels -->
        <div v-if="task.status === 'processing'" class="py-4">
          <!-- Progress bar -->
          <div class="h-1.5 bg-gray-700 rounded-full mb-5 overflow-hidden">
            <div
              class="h-full bg-gradient-to-r from-blue-600 to-blue-400 rounded-full transition-all duration-1000"
              :style="{ width: processingProgress + '%' }"
            ></div>
          </div>
          <!-- Skeleton lines -->
          <div class="space-y-3 max-w-md mx-auto mb-5">
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-full"></div>
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-3/4"></div>
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-1/2"></div>
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-5/6"></div>
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-2/3"></div>
          </div>
          <!-- Dynamic step label -->
          <p class="text-center text-gray-400 text-sm transition-all duration-500">
            <span class="mr-1.5">{{ processingStep.icon }}</span>{{ processingStep.text }}
          </p>
        </div>

        <!-- Completed state: result not yet loaded (defensive fallback for slow networks) -->
        <div v-if="task.status === 'completed' && !task.result" class="py-6 text-center">
          <div class="space-y-3 max-w-md mx-auto mb-4">
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-full"></div>
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-4/5"></div>
            <div class="animate-shimmer h-3 rounded bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] w-3/5"></div>
          </div>
          <p class="text-gray-500 text-sm">Loading cached result...</p>
        </div>

        <!-- Completed State — only render when result data is fully loaded -->
        <div v-if="task.status === 'completed' && task.result">
          <!-- Video Preview — compact horizontal layout -->
          <div v-if="thumbnailUrl && !thumbnailError" class="flex gap-4 items-start mb-5">
            <img
              :src="thumbnailUrl"
              @error="thumbnailError = true"
              class="w-[150px] flex-shrink-0 rounded-lg object-cover bg-gray-700"
              style="aspect-ratio: 16/9"
              :alt="task.title || 'YouTube video thumbnail'"
              loading="lazy"
            />
            <div class="min-w-0 flex-1 pt-0.5">
              <h2 class="text-sm font-semibold text-white leading-snug line-clamp-3">
                {{ task.title || 'YouTube Video' }}
              </h2>
              <p class="mt-1.5 text-xs text-gray-500 truncate">{{ task.youtube_url }}</p>
              <p v-if="task.duration_sec" class="mt-1 text-xs text-gray-600">
                {{ formatDuration(task.duration_sec) }}
              </p>
            </div>
          </div>

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
              <div v-html="renderedSummary" class="prose prose-invert prose-sm max-w-none text-gray-300"></div>
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

          <!-- Transcript Panel — paragraph-chunked for readability -->
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
                <!-- Paragraph-chunked transcript with estimated timecodes -->
                <div
                  v-for="(chunk, index) in groupedTranscript"
                  :key="index"
                  class="mb-3 last:mb-0 text-sm leading-relaxed text-gray-300 break-words"
                >
                  <a
                    v-if="chunk.timeSec !== null && task.video_id"
                    :href="`https://youtube.com/watch?v=${task.video_id}&t=${chunk.timeSec}`"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-block text-blue-400 hover:text-blue-300 font-mono text-xs mr-2 transition-colors shrink-0"
                    :title="`Open YouTube at ${formatTimecode(chunk.timeSec)}`"
                  >[{{ formatTimecode(chunk.timeSec) }}]</a><span
                    v-else-if="chunk.timeSec === null && index === 0"
                  ></span>{{ chunk.text }}
                </div>
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
import { ref, computed, onUnmounted, onMounted } from 'vue';
import axios from 'axios';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

const youtubeUrl = ref('');
const task = ref(null);
const error = ref(null);
const isLoading = ref(false);
const urlValidationError = ref(null);
const copySummaryLabel = ref('Copy Summary');
const copyTranscriptLabel = ref('Copy Transcript');
const activeTab = ref('summary');
const thumbnailError = ref(false);

// Search state
const searchQuery = ref('');
const searchResults = ref(null);
const searchLoading = ref(false);
const searchError = ref(null);
const searchPage = ref(1);
const searchHasMore = ref(false);
let searchDebounceTimer = null;

// Recently Transcribed
const recentTasks = ref([]);

// Processing step tracking
const processingStartedAt = ref(null);
const processingElapsed = ref(0);
let elapsedTimer = null;

const PROCESSING_STEPS = [
  { maxSec: 10,       icon: '📥', text: 'Downloading audio track...' },
  { maxSec: 60,       icon: '🤖', text: 'Transcribing speech with AI (Whisper / Groq)...' },
  { maxSec: 90,       icon: '✨', text: 'Generating smart summary...' },
  { maxSec: Infinity, icon: '⏳', text: 'Finalizing... almost done!' },
];

const processingStep = computed(() => {
  const elapsed = processingElapsed.value;
  return PROCESSING_STEPS.find(s => elapsed < s.maxSec) ?? PROCESSING_STEPS[PROCESSING_STEPS.length - 1];
});

const processingProgress = computed(() => {
  const total = task.value?.estimated_completion_sec || 90;
  return Math.min(Math.round((processingElapsed.value / total) * 100), 95);
});

function startElapsedTimer() {
  processingStartedAt.value = Date.now();
  processingElapsed.value = 0;
  stopElapsedTimer();
  elapsedTimer = setInterval(() => {
    processingElapsed.value = Math.round((Date.now() - processingStartedAt.value) / 1000);
  }, 1000);
}

function stopElapsedTimer() {
  if (elapsedTimer) {
    clearInterval(elapsedTimer);
    elapsedTimer = null;
  }
}

const thumbnailUrl = computed(() => {
  if (!task.value?.video_id) return null;
  return `https://img.youtube.com/vi/${task.value.video_id}/maxresdefault.jpg`;
});

const renderedSummary = computed(() => {
  const raw = task.value?.result?.summary ?? '';
  if (!raw) return '';
  return DOMPurify.sanitize(marked.parse(raw));
});

/**
 * Split transcript into ~80-word chunks with estimated timecodes.
 * Time is derived from video duration_sec proportional to word position.
 * Falls back to timeSec=null when duration is unavailable.
 */
const groupedTranscript = computed(() => {
  const text = task.value?.result?.transcript ?? '';
  if (!text) return [];

  const CHUNK_SIZE = 80;
  const words = text.split(/\s+/);
  const totalWords = words.length;
  const durationSec = task.value?.duration_sec ?? 0;
  const wordsPerSec = durationSec > 0 && totalWords > 0
    ? totalWords / durationSec
    : 0;

  const chunks = [];
  for (let i = 0; i < words.length; i += CHUNK_SIZE) {
    const timeSec = wordsPerSec > 0
      ? Math.round(i / wordsPerSec)
      : null;
    chunks.push({
      text: words.slice(i, i + CHUNK_SIZE).join(' '),
      timeSec,
    });
  }
  return chunks;
});

/** Format seconds → "MM:SS" timecode label (e.g. "12:05") */
function formatTimecode(sec) {
  const m = Math.floor(sec / 60);
  const s = sec % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

/** Format seconds → "12:05" or "1:02:45" */
function formatDuration(sec) {
  if (!sec) return '';
  const h = Math.floor(sec / 3600);
  const m = Math.floor((sec % 3600) / 60);
  const s = sec % 60;
  if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  return `${m}:${String(s).padStart(2, '0')}`;
}

/** Format ISO date string → "May 10, 2026" */
function formatDate(isoString) {
  if (!isoString) return '';
  const d = new Date(isoString);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

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

// ---- Search ----

function onSearchInput() {
  const q = searchQuery.value.trim();

  // Reset results when query is cleared
  if (q === '') {
    cancelSearchDebounce();
    searchResults.value = null;
    searchError.value = null;
    searchPage.value = 1;
    searchHasMore.value = false;
    return;
  }

  // Don't search for single character
  if (q.length < 2) {
    cancelSearchDebounce();
    searchResults.value = null;
    searchError.value = null;
    searchPage.value = 1;
    searchHasMore.value = false;
    return;
  }

  cancelSearchDebounce();
  searchDebounceTimer = setTimeout(() => {
    searchPage.value = 1;
    performSearch(q, 1);
  }, 300);
}

function searchLoadMore() {
  const q = searchQuery.value.trim();
  if (q.length < 2 || searchLoading.value || !searchHasMore.value) return;
  const nextPage = searchPage.value + 1;
  performSearch(q, nextPage, true);
}

async function performSearch(q, page, append = false) {
  searchLoading.value = true;
  searchError.value = null;

  try {
    const { data } = await axios.get('/api/search', {
      params: { q, page, per_page: 15 },
    });

    if (append) {
      searchResults.value = [...searchResults.value, ...data.data];
    } else {
      searchResults.value = data.data;
    }

    searchPage.value = data.meta.current_page;
    searchHasMore.value = data.meta.current_page < data.meta.last_page;
  } catch (e) {
    if (!append) {
      searchResults.value = [];
    }
    searchError.value = e.response?.data?.error?.message || 'Search failed. Please try again.';
  } finally {
    searchLoading.value = false;
  }
}

function cancelSearchDebounce() {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = null;
  }
}

// ---- Recently Transcribed ----

async function fetchRecentTasks() {
  try {
    const { data } = await axios.get('/api/history', {
      params: { status: 'completed', per_page: 10, page: 1 },
    });
    recentTasks.value = data.data || [];
  } catch {
    // Silently ignore — history is decorative
  }
}

// ---- Transcription ----

async function submitUrl() {
  error.value = null;
  urlValidationError.value = null;

  if (!youtubeUrl.value.trim()) {
    urlValidationError.value = 'Please paste a YouTube URL to get started.';
    return;
  }

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
    thumbnailError.value = false;

    if (data.status === 'completed' && data.result) {
      // Full data already in POST response (cached duplicate) — no polling needed
      return;
    }

    if (data.status === 'processing') startElapsedTimer();
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

      // Start elapsed timer when task transitions to processing
      if (data.status === 'processing' && task.value?.status !== 'processing') {
        startElapsedTimer();
      }

      task.value = data;

      if (data.status === 'completed' || data.status === 'failed') {
        stopPolling();
        stopElapsedTimer();
      }

      // Stop polling after 120 attempts (~10 minutes)
      if (attempts > 120) {
        stopPolling();
        stopElapsedTimer();
      }
    } catch (e) {
      error.value = 'Failed to fetch task status.';
      stopPolling();
      stopElapsedTimer();
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

onMounted(async () => {
  fetchRecentTasks();

  // If navigated from /history with ?task_id=..., load that task
  const params = new URLSearchParams(window.location.search);
  const taskId = params.get('task_id');
  if (taskId) {
    try {
      const { data } = await axios.get(`/api/transcribe/${taskId}`);
      task.value = data;
      activeTab.value = 'summary';
      thumbnailError.value = false;

      if (data.status === 'completed' && data.result) {
        // Already completed — no polling needed
        return;
      }

      if (data.status === 'processing') startElapsedTimer();
      if (data.status !== 'completed' && data.status !== 'failed') {
        startPolling(taskId);
      }
    } catch {
      error.value = 'Failed to load task.';
    }
  }
});

onUnmounted(() => {
  stopPolling();
  stopElapsedTimer();
  cancelSearchDebounce();
});
</script>
