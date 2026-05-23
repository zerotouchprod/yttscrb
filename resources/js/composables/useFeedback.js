import { ref } from 'vue';

export function useFeedback() {
  const state = ref('idle'); // idle | loading | success | error
  const errorMessage = ref('');

  async function submitFeedback(message, email) {
    state.value = 'loading';
    errorMessage.value = '';

    try {
      const response = await fetch('/api/feedback', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ message, email: email || null }),
      });

      if (response.status === 429) {
        state.value = 'error';
        errorMessage.value = 'Too many requests. Please try again later.';
        return;
      }

      if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        state.value = 'error';
        errorMessage.value = data.message || 'Failed to send feedback. Please try again.';
        return;
      }

      state.value = 'success';
    } catch {
      state.value = 'error';
      errorMessage.value = 'Network error. Please check your connection.';
    }
  }

  function reset() {
    state.value = 'idle';
    errorMessage.value = '';
  }

  return { state, errorMessage, submitFeedback, reset };
}
