<template>
  <div v-if="topics.length" class="mt-10 mb-8">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Popular Topics</h2>
      <a href="/topics" class="text-xs text-blue-400 hover:text-blue-300 transition-colors">View all →</a>
    </div>
    <div class="flex flex-wrap gap-2">
      <a
        v-for="topic in topics"
        :key="topic.slug"
        :href="'/topic/' + topic.slug"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-800/60 border border-gray-700/50 rounded-full text-xs text-gray-300 hover:text-white hover:border-blue-500/50 hover:bg-gray-700/60 transition-all"
      >
        <span>#{{ topic.name }}</span>
        <span class="text-[10px] bg-gray-900/80 text-gray-500 px-1.5 py-0.5 rounded-full">{{ topic.video_count }}</span>
      </a>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const topics = ref([]);

onMounted(async () => {
  try {
    const res = await fetch('/api/topics/popular');
    if (res.ok) {
      topics.value = await res.json();
    }
  } catch {
    // silent
  }
});
</script>
