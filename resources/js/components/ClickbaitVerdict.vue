<template>
  <div v-if="verdict" class="bg-gray-800/60 rounded-xl p-4 border border-gray-700">
    <div class="flex items-center gap-3 mb-2">
      <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Clickbait Check</h4>
      <span
        :class="scoreColorClass"
        class="text-xs font-bold px-2 py-0.5 rounded-full"
      >
        {{ verdict.score }}% Legit
      </span>
    </div>

    <!-- Health-bar style gauge -->
    <div class="h-2 bg-gray-700 rounded-full overflow-hidden mb-3">
      <div
        :class="gaugeColorClass"
        class="h-full rounded-full transition-all duration-700"
        :style="{ width: verdict.score + '%' }"
      ></div>
    </div>

    <!-- Verdict comment -->
    <p class="text-gray-300 text-sm leading-relaxed italic">
      "{{ verdict.comment }}"
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  verdict: { type: Object, default: null },
});

const scoreColorClass = computed(() => {
  if (!props.verdict) return '';
  if (props.verdict.score >= 80) return 'bg-green-900/60 text-green-400 border border-green-700/40';
  if (props.verdict.score >= 50) return 'bg-yellow-900/60 text-yellow-400 border border-yellow-700/40';
  return 'bg-red-900/60 text-red-400 border border-red-700/40';
});

const gaugeColorClass = computed(() => {
  if (!props.verdict) return '';
  if (props.verdict.score >= 80) return 'bg-gradient-to-r from-green-600 to-green-400';
  if (props.verdict.score >= 50) return 'bg-gradient-to-r from-yellow-600 to-yellow-400';
  return 'bg-gradient-to-r from-red-600 to-red-400';
});
</script>
