<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60" @click="$emit('close')"></div>

    <!-- Modal -->
    <div class="relative bg-gray-800 border border-gray-700 rounded-2xl w-full max-w-md p-6 shadow-2xl">
      <!-- Success state -->
      <div v-if="state === 'success'" class="text-center py-8">
        <div class="mx-auto w-16 h-16 bg-green-900/50 border border-green-700 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold text-white mb-2">Thank You!</h3>
        <p class="text-gray-400">Your feedback has been sent.</p>
        <button
          @click="$emit('close'); reset()"
          class="mt-6 px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
        >
          Close
        </button>
      </div>

      <!-- Form state -->
      <template v-else>
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-white">Send Feedback</h3>
          <button @click="$emit('close'); reset()" class="text-gray-400 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label for="feedback-message" class="block text-sm text-gray-300 mb-1">Message *</label>
            <textarea
              id="feedback-message"
              v-model="message"
              rows="4"
              maxlength="2000"
              required
              placeholder="Tell us what you think..."
              class="w-full bg-gray-900 border border-gray-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 transition-colors resize-none"
            ></textarea>
            <p class="text-xs text-gray-500 mt-1 text-right">{{ message.length }}/2000</p>
          </div>

          <div>
            <label for="feedback-email" class="block text-sm text-gray-300 mb-1">Email (optional)</label>
            <input
              id="feedback-email"
              v-model="email"
              type="email"
              maxlength="255"
              placeholder="your@email.com"
              class="w-full bg-gray-900 border border-gray-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 transition-colors"
            />
          </div>

          <div v-if="state === 'error'" class="bg-red-900/50 border border-red-700 rounded-lg p-3">
            <p class="text-red-300 text-sm">{{ errorMessage }}</p>
          </div>

          <button
            type="submit"
            :disabled="state === 'loading' || !message.trim()"
            class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white font-medium rounded-lg transition-colors flex items-center justify-center gap-2"
          >
            <svg v-if="state === 'loading'" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ state === 'loading' ? 'Sending...' : 'Send Feedback' }}
          </button>
        </form>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useFeedback } from '../composables/useFeedback.js';

const props = defineProps({
  open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const { state, errorMessage, submitFeedback, reset } = useFeedback();

const message = ref('');
const email = ref('');

function handleSubmit() {
  submitFeedback(message.value, email.value);
}
</script>
