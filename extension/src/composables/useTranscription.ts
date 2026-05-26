import { ref } from 'vue';
import type { TranscribeTaskResponse, Summary } from '../types/api';

export type PopupState = 'not-youtube' | 'idle' | 'loading' | 'success' | 'error';

export function useTranscription() {
  const state = ref<PopupState>('idle');
  const errorMessage = ref('');
  const summary = ref<Summary | null>(null);
  const publicPageUrl = ref<string | null>(null);
  const videoTitle = ref<string | null>(null);

  async function detectYouTubeTab(): Promise<boolean> {
    return new Promise((resolve) => {
      chrome.runtime.sendMessage(
        { type: 'GET_ACTIVE_TAB_URL' },
        (response) => {
          const url: string | null = response?.url ?? null;
          resolve(url?.includes('youtube.com/watch') ?? false);
        }
      );
    });
  }

  async function getCurrentTabUrl(): Promise<string | null> {
    return new Promise((resolve) => {
      chrome.runtime.sendMessage(
        { type: 'GET_ACTIVE_TAB_URL' },
        (response) => {
          resolve(response?.url ?? null);
        }
      );
    });
  }

  async function summarize(): Promise<void> {
    const url = await getCurrentTabUrl();
    if (!url) {
      state.value = 'error';
      errorMessage.value = 'Could not access current tab.';
      return;
    }

    state.value = 'loading';
    errorMessage.value = '';

    try {
      const response = await chrome.runtime.sendMessage({
        type: 'SUMMARIZE_REQUEST',
        youtubeUrl: url,
      });

      if (response.type === 'SUMMARIZE_SUCCESS') {
        const task: TranscribeTaskResponse = response.data;
        summary.value = task.result?.summary ?? null;
        videoTitle.value = task.title ?? null;

        const publicPage = task._links?.public_page;
        publicPageUrl.value = publicPage
          ? `https://tubesum.app${publicPage}`
          : null;

        state.value = 'success';
      } else {
        state.value = 'error';
        errorMessage.value = response.error ?? 'Unknown error';
      }
    } catch (err) {
      state.value = 'error';
      errorMessage.value = err instanceof Error ? err.message : 'Failed to summarize.';
    }
  }

  async function initialize(): Promise<void> {
    const isYouTube = await detectYouTubeTab();
    state.value = isYouTube ? 'idle' : 'not-youtube';
  }

  function reset(): void {
    state.value = 'idle';
    errorMessage.value = '';
    summary.value = null;
    publicPageUrl.value = null;
    videoTitle.value = null;
  }

  return {
    state,
    errorMessage,
    summary,
    publicPageUrl,
    videoTitle,
    initialize,
    summarize,
    reset,
  };
}
