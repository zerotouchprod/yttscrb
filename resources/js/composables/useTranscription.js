import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

export function useTranscription() {
  const youtubeUrl = ref('');
  const task = ref(null);
  const error = ref(null);
  const isLoading = ref(false);
  const urlValidationError = ref(null);
  const copySummaryLabel = ref('Copy Summary');
  const copyTranscriptLabel = ref('Copy Transcript');
  const activeTab = ref('summary');
  const thumbnailError = ref(false);
  const recentTasks = ref([]);
  const processingStartedAt = ref(null);
  const processingElapsed = ref(0);
  let elapsedTimer = null;

  const PROCESSING_STEPS = [
    { maxSec: 10,       icon: '\u{1F4E5}', text: 'Downloading audio track...' },
    { maxSec: 60,       icon: '\u{1F916}', text: 'Transcribing speech with AI (Whisper / Groq)...' },
    { maxSec: 90,       icon: '\u{2728}', text: 'Generating smart summary...' },
    { maxSec: Infinity, icon: '\u{23F3}', text: 'Finalizing... almost done!' },
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
    if (elapsedTimer) { clearInterval(elapsedTimer); elapsedTimer = null; }
  }

  const thumbnailUrl = computed(() => {
    if (!task.value?.video_id) return null;
    return 'https://img.youtube.com/vi/' + task.value.video_id + '/maxresdefault.jpg';
  });

  const renderedSummary = computed(() => {
    const summary = task.value?.result?.summary;
    if (!summary || typeof summary !== 'object') return '';
    return summary;
  });

  const groupedTranscript = computed(() => {
    const text = task.value?.result?.transcript ?? '';
    if (!text) return [];
    const CHUNK_SIZE = 80;
    const words = text.split(/\s+/);
    const totalWords = words.length;
    const durationSec = task.value?.duration_sec ?? 0;
    const wordsPerSec = durationSec > 0 && totalWords > 0 ? totalWords / durationSec : 0;
    const chunks = [];
    for (let i = 0; i < words.length; i += CHUNK_SIZE) {
      const timeSec = wordsPerSec > 0 ? Math.round(i / wordsPerSec) : null;
      chunks.push({ text: words.slice(i, i + CHUNK_SIZE).join(' '), timeSec });
    }
    return chunks;
  });

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
    return /^https?:\/\/(www\.)?youtube\.com\/watch\?v=[\w-]+/.test(url)
      || /^https?:\/\/(www\.)?youtu\.be\/[\w-]+/.test(url)
      || /^https?:\/\/(m\.)?youtube\.com\/watch\?v=[\w-]+/.test(url);
  }

  async function submitUrl() {
    error.value = null;
    urlValidationError.value = null;
    if (!youtubeUrl.value.trim()) {
      urlValidationError.value = 'Please paste a YouTube URL to get started.';
      return;
    }
    if (!isValidYouTubeUrl(youtubeUrl.value.trim())) {
      urlValidationError.value = 'Please enter a valid YouTube URL.';
      return;
    }
    isLoading.value = true;
    try {
      const { data } = await axios.post('/api/transcribe', { youtube_url: youtubeUrl.value });
      task.value = data;
      activeTab.value = 'summary';
      thumbnailError.value = false;
      if (data.status === 'completed' && data.result) return;
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
        const { data } = await axios.get('/api/transcribe/' + taskId);
        if (data.status === 'processing' && task.value?.status !== 'processing') startElapsedTimer();
        task.value = data;
        if (data.status === 'completed' || data.status === 'failed') { stopPolling(); stopElapsedTimer(); }
        if (attempts > 120) { stopPolling(); stopElapsedTimer(); }
      } catch (e) {
        error.value = 'Failed to fetch task status.';
        stopPolling();
        stopElapsedTimer();
      }
    }, 5000);
  }

  function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

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

  function summaryAsText() {
    const s = task.value?.result?.summary;
    if (!s || typeof s !== 'object') return '';
    let text = (s.introduction || '') + '\n\n';
    for (const kp of s.key_points || []) {
      text += `[${kp.timecode}] ${kp.title}: ${kp.details}\n`;
    }
    if (s.conclusion) text += '\n' + s.conclusion;
    return text;
  }

  async function copySummary() {
    const text = summaryAsText();
    if (!text) return;
    try {
      await navigator.clipboard.writeText(text);
      copySummaryLabel.value = 'Copied!';
      setTimeout(() => { copySummaryLabel.value = 'Copy Summary'; }, 2000);
    } catch {
      copySummaryLabel.value = 'Failed';
      setTimeout(() => { copySummaryLabel.value = 'Copy Summary'; }, 2000);
    }
  }

  function retry() { task.value = null; error.value = null; submitUrl(); }

  async function fetchRecentTasks() {
    try {
      const { data } = await axios.get('/api/history', { params: { public: 1, per_page: 10, page: 1 } });
      recentTasks.value = data.data || [];
    } catch { /* decorative */ }
  }

  onMounted(async () => {
    fetchRecentTasks();
    const params = new URLSearchParams(window.location.search);
    const taskId = params.get('task_id');
    if (taskId) {
      try {
        const { data } = await axios.get('/api/transcribe/' + taskId);
        task.value = data;
        activeTab.value = 'summary';
        thumbnailError.value = false;
        if (data.status === 'completed' && data.result) return;
        if (data.status === 'processing') startElapsedTimer();
        if (data.status !== 'completed' && data.status !== 'failed') startPolling(taskId);
      } catch {
        error.value = 'Failed to load task.';
      }
    }
  });

  onUnmounted(() => { stopPolling(); stopElapsedTimer(); });

  return {
    youtubeUrl, task, error, isLoading, urlValidationError,
    copySummaryLabel, copyTranscriptLabel, activeTab, thumbnailError,
    recentTasks, processingElapsed, processingStep, processingProgress,
    thumbnailUrl, renderedSummary, groupedTranscript, statusBadgeClass,
    submitUrl, copyTranscript, copySummary, retry,
  };
}
