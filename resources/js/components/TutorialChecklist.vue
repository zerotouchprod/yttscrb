<template>
  <div v-if="steps.length" class="space-y-1">
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
        </svg>
        Tutorial Checklist
      </h3>
      <span class="text-xs text-gray-500">{{ completedCount }}/{{ steps.length }} done</span>
    </div>

    <div
      v-for="(step, index) in steps"
      :key="step.step"
      class="flex items-start gap-3 rounded-lg p-2.5 border border-gray-700/40 bg-gray-800/30 hover:bg-gray-800/50 transition-colors"
    >
      <!-- Checkbox -->
      <label class="shrink-0 flex items-center cursor-pointer mt-0.5">
        <input
          type="checkbox"
          :checked="isChecked(step.step)"
          @change="toggle(step.step)"
          class="sr-only"
        />
        <span
          :class="[
            'flex items-center justify-center w-5 h-5 rounded border-2 transition-all',
            isChecked(step.step)
              ? 'bg-emerald-600 border-emerald-500 text-white'
              : 'border-gray-600 hover:border-gray-400'
          ]"
        >
          <svg v-if="isChecked(step.step)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
          </svg>
        </span>
      </label>

      <!-- Step number -->
      <span
        :class="[
          'shrink-0 text-xs font-mono font-medium w-5 text-center',
          isChecked(step.step) ? 'text-gray-600' : 'text-gray-400'
        ]"
      >
        {{ step.step }}
      </span>

      <!-- Timecode button -->
      <button
        v-if="step.time"
        @click="handleSeek(step.time)"
        class="shrink-0 inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-mono font-medium text-blue-400 bg-blue-500/10 hover:bg-blue-500/25 rounded border border-blue-500/30 transition-all hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
        :title="'Jump to ' + step.time"
      >
        <svg class="w-2 h-2 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        {{ step.time }}
      </button>

      <!-- Action text -->
      <span
        :class="[
          'text-sm leading-relaxed flex-1',
          isChecked(step.step) ? 'text-gray-500 line-through' : 'text-gray-200'
        ]"
      >
        {{ step.action }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
  /**
   * Array of { step: number, time: string, action: string } objects.
   */
  steps: { type: Array, default: () => [] },
  /**
   * Unique task identifier for localStorage key.
   */
  taskId: { type: String, required: true },
  /**
   * Callback(seconds: number) — parent handles YouTube seek.
   */
  onSeek: { type: Function, default: null },
});

const STORAGE_KEY = 'tutorial_checklist';

/** @type {import('vue').Ref<Record<string, boolean>>} */
const checkedState = ref({});

/** Load persisted state for this taskId. */
function loadState() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return;
    const all = JSON.parse(raw);
    if (all && typeof all === 'object' && all[props.taskId]) {
      checkedState.value = { ...all[props.taskId] };
    }
  } catch {
    // Corrupted storage — reset silently.
  }
}

/** Persist current state to localStorage. */
function saveState() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    const all = raw ? JSON.parse(raw) : {};
    all[props.taskId] = { ...checkedState.value };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
  } catch {
    // Storage full or unavailable — ignore.
  }
}

function isChecked(stepNumber) {
  return !!checkedState.value[String(stepNumber)];
}

function toggle(stepNumber) {
  const key = String(stepNumber);
  checkedState.value = {
    ...checkedState.value,
    [key]: !checkedState.value[key],
  };
  saveState();
}

/** Parse "MM:SS" or "HH:MM:SS" → seconds. */
function parseTimecode(timecode) {
  if (!timecode) return 0;
  const parts = timecode.split(':').map(Number);
  if (parts.length === 3) return parts[0] * 3600 + parts[1] * 60 + parts[2];
  if (parts.length === 2) return parts[0] * 60 + parts[1];
  return 0;
}

function handleSeek(timecode) {
  if (props.onSeek) props.onSeek(parseTimecode(timecode));
}

const completedCount = computed(() => {
  let count = 0;
  for (const step of props.steps) {
    if (checkedState.value[String(step.step)]) count++;
  }
  return count;
});

// Reload when taskId changes
watch(() => props.taskId, () => {
  loadState();
}, { immediate: true });
</script>
