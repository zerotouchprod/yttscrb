<template>
  <div class="min-h-screen bg-gray-900 text-gray-100 flex flex-col items-center pb-20">
    <NavBar
      :searchQuery="searchQuery"
      :searchLoading="searchLoading"
      @update:searchQuery="searchQuery = $event"
      @searchInput="onSearchInput"
    />
    <HeroSection />
    <main class="w-full max-w-3xl px-4 sm:px-6">
      <TranscribeForm
        :youtubeUrl="youtubeUrl"
        :isLoading="isLoading"
        :urlValidationError="urlValidationError"
        @submit="submitUrl"
        @update:youtubeUrl="youtubeUrl = $event"
        @clearError="urlValidationError = null"
      />
      <div v-if="error" class="bg-red-900/50 border border-red-700 rounded-xl p-4 mb-8 max-w-full overflow-hidden" role="alert">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <p class="text-red-300 break-words">{{ error }}</p>
        </div>
      </div>
      <TaskStatusCard
        :task="task"
        :statusBadgeClass="statusBadgeClass"
        :processingProgress="processingProgress"
        :processingStep="processingStep"
        :renderedSummary="renderedSummary"
        :groupedTranscript="groupedTranscript"
        :copySummaryLabel="copySummaryLabel"
        :copyTranscriptLabel="copyTranscriptLabel"
        v-model:activeTab="activeTab"
        @copySummary="copySummary"
        @copyTranscript="copyTranscript"
        @retry="retry"
      />
      <PublicLibrary :recentTasks="recentTasks" :hideWhenTask="!!task" />
      <SearchResults
        :searchResults="searchResults"
        :searchLoading="searchLoading"
        :searchError="searchError"
        :searchHasMore="searchHasMore"
        :searchQuery="searchQuery"
        @loadMore="searchLoadMore"
      />
    </main>
    <footer class="w-full border-t border-slate-800 bg-[#0f172a] mt-20">
      <div class="max-w-4xl mx-auto px-4 py-8 sm:py-12">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8 text-sm">
          <div class="col-span-2 md:col-span-1">
            <span class="text-xl font-bold text-slate-200 tracking-tight">TubeSum</span>
            <p class="mt-2 text-slate-500">Save hours of watching. Get the text in seconds.</p>
          </div>
          <div>
            <h3 class="font-semibold text-slate-100 mb-3">Product</h3>
            <ul class="space-y-2 text-slate-400">
              <li><a href="/pricing" class="hover:text-blue-400 transition-colors">Pricing</a></li>
              <li><a href="/history" class="hover:text-blue-400 transition-colors">Public Library</a></li>
              <li><a href="#" class="hover:text-blue-400 transition-colors">Chrome Extension <span class="text-[10px] bg-slate-800 border border-slate-700 text-slate-300 px-1.5 py-0.5 rounded ml-1">Soon</span></a></li>
            </ul>
          </div>
          <div>
            <h3 class="font-semibold text-slate-100 mb-3">Legal</h3>
            <ul class="space-y-2 text-slate-400">
              <li><a href="/terms" class="hover:text-blue-400 transition-colors">Terms of Service</a></li>
              <li><a href="/privacy" class="hover:text-blue-400 transition-colors">Privacy Policy</a></li>
              <li><a href="/dmca" class="hover:text-red-400 transition-colors">DMCA / Removal</a></li>
            </ul>
          </div>
          <div>
            <h3 class="font-semibold text-slate-100 mb-3">Connect</h3>
            <ul class="space-y-2 text-slate-400">
              <li><a href="https://x.com/tubesumapp" target="_blank" rel="noopener noreferrer" class="hover:text-blue-400 transition-colors">Twitter (X)</a></li>
              <li><button @click="feedbackOpen = true" class="hover:text-blue-400 transition-colors">Contact Support</button></li>
            </ul>
          </div>
        </div>
        <div class="border-t border-slate-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-slate-500">
          <p>&copy; {{ new Date().getFullYear() }} TubeSum.app. All rights reserved.</p>
          <p>Built with Laravel & Tailwind</p>
        </div>
      </div>
    </footer>
    <FeedbackModal :open="feedbackOpen" @close="feedbackOpen = false" />
  </div>
</template>

<script setup>
import NavBar from './components/NavBar.vue';
import HeroSection from './components/HeroSection.vue';
import TranscribeForm from './components/TranscribeForm.vue';
import TaskStatusCard from './components/TaskStatusCard.vue';
import PublicLibrary from './components/PublicLibrary.vue';
import SearchResults from './components/SearchResults.vue';
import FeedbackModal from './components/FeedbackModal.vue';
import { ref } from 'vue';
import { useSearch } from './composables/useSearch.js';
import { useTranscription } from './composables/useTranscription.js';

const feedbackOpen = ref(false);

const {
  searchQuery, searchResults, searchLoading, searchError, searchHasMore,
  onSearchInput, searchLoadMore,
} = useSearch();

const {
  youtubeUrl, task, error, isLoading, urlValidationError,
  copySummaryLabel, copyTranscriptLabel, activeTab, thumbnailError,
  recentTasks, processingStep, processingProgress,
  thumbnailUrl, renderedSummary, groupedTranscript, statusBadgeClass,
  submitUrl, copyTranscript, copySummary, retry,
} = useTranscription();
</script>
