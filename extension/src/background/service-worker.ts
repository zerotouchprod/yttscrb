import type {
  BackgroundMessage,
  TranscribePostResponse,
  TranscribeTaskResponse,
  ApiError,
} from '../types/api';

const API_BASE = 'https://tubesum.app/api';
const POLL_INTERVAL_MS = 3000; // Poll every 3 seconds
const MAX_POLL_ATTEMPTS = 40;   // Max 2 minutes of polling

/**
 * POST /api/transcribe — create a new transcription task.
 * Returns 202 (new pending) or 200 (dedup, already completed).
 */
async function createTranscription(youtubeUrl: string): Promise<TranscribePostResponse> {
  const response = await fetch(`${API_BASE}/transcribe`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ youtube_url: youtubeUrl }),
  });

  if (!response.ok) {
    const errorBody: ApiError = await response.json();
    throw new Error(errorBody.error.message);
  }

  return response.json() as Promise<TranscribePostResponse>;
}

/**
 * GET /api/transcribe/{id} — poll for task status.
 */
async function getTaskStatus(taskId: string): Promise<TranscribeTaskResponse> {
  const response = await fetch(`${API_BASE}/transcribe/${taskId}`, {
    headers: { 'Accept': 'application/json' },
  });

  if (!response.ok) {
    const errorBody: ApiError = await response.json();
    throw new Error(errorBody.error.message);
  }

  return response.json() as Promise<TranscribeTaskResponse>;
}

/**
 * Poll until task reaches 'completed' or 'failed', then return final state.
 */
async function pollUntilDone(taskId: string): Promise<TranscribeTaskResponse> {
  for (let i = 0; i < MAX_POLL_ATTEMPTS; i++) {
    const task = await getTaskStatus(taskId);

    if (task.status === 'completed' || task.status === 'failed') {
      return task;
    }

    // Notify content script about progress
    chrome.runtime.sendMessage({
      type: 'SUMMARIZE_PROGRESS',
      taskId,
      status: task.status,
    }).catch(() => {
      // Content script may not be listening — that's fine
    });

    await new Promise(resolve => setTimeout(resolve, POLL_INTERVAL_MS));
  }

  throw new Error('Transcription timed out. Please try again.');
}

// === Message Listener ===

chrome.runtime.onMessage.addListener(
  (message: BackgroundMessage, _sender, sendResponse: (response?: unknown) => void) => {
    if (message.type === 'SUMMARIZE_REQUEST') {
      handleSummarizeRequest(message.youtubeUrl)
        .then(sendResponse)
        .catch(err => sendResponse({ type: 'SUMMARIZE_ERROR', error: err.message }));
      return true; // Keep channel open for async response
    }

    if (message.type === 'GET_ACTIVE_TAB_URL') {
      chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
        const url = tabs[0]?.url ?? null;
        sendResponse({ type: 'ACTIVE_TAB_URL', url });
      });
      return true;
    }
  }
);

async function handleSummarizeRequest(youtubeUrl: string) {
  // 1. Create task
  const createResponse = await createTranscription(youtubeUrl);

  // 2. If already completed (dedup), return immediately
  if (createResponse.status === 'completed') {
    return {
      type: 'SUMMARIZE_SUCCESS' as const,
      taskId: createResponse.task_id,
      data: createResponse,
    };
  }

  // 3. Otherwise, poll until done
  const finalTask = await pollUntilDone(createResponse.task_id);

  if (finalTask.status === 'failed') {
    return {
      type: 'SUMMARIZE_ERROR' as const,
      error: finalTask.error_message ?? 'Unknown error',
    };
  }

  return {
    type: 'SUMMARIZE_SUCCESS' as const,
    taskId: finalTask.task_id,
    data: finalTask,
  };
}

// Log startup (useful for debugging service worker wake-up)
console.log('[TubeSum] Background service worker started.');
