<template>
  <div v-if="flashcards.length" class="space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
        <svg class="w-3.5 h-3.5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
        </svg>
        Study Flashcards ({{ flashcards.length }})
      </h3>
      <button
        @click="exportCsv"
        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-purple-400 transition-colors border border-purple-500/30 rounded-lg bg-purple-500/10 hover:bg-purple-500/20"
      >
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Download CSV
      </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
      <div
        v-for="(card, index) in flashcards"
        :key="index"
        class="relative p-4 transition-all border border-gray-700 rounded-lg cursor-pointer bg-gray-800/50 hover:border-purple-500 group"
        @click="toggleCard(index)"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0 flex-1">
            <template v-if="flipped[index]">
              <!-- Answer side -->
              <p class="text-sm text-gray-200 leading-relaxed">{{ card.answer }}</p>
              <p class="mt-2 text-xs text-purple-400">
                {{ card.source_timecode }}
              </p>
            </template>
            <template v-else>
              <!-- Question side -->
              <p class="text-sm font-medium text-gray-200">{{ card.question }}</p>
              <div class="flex items-center gap-2 mt-2">
                <span class="text-xs capitalize px-1.5 py-0.5 rounded"
                      :class="difficultyClass(card.difficulty)">
                  {{ card.difficulty }}
                </span>
                <span class="text-xs text-gray-500">Click to reveal answer</span>
              </div>
            </template>
          </div>
          <svg class="w-4 h-4 text-gray-600 shrink-0 group-hover:text-purple-400 transition-colors"
               :class="{ 'rotate-180': flipped[index] }"
               fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue'

const props = defineProps({
  flashcards: { type: Array, required: true },
})

const flipped = reactive({})

function toggleCard(index) {
  flipped[index] = !flipped[index]
}

function difficultyClass(difficulty) {
  switch (difficulty) {
    case 'easy':   return 'text-green-400 bg-green-500/10'
    case 'medium': return 'text-amber-400 bg-amber-500/10'
    case 'hard':   return 'text-red-400 bg-red-500/10'
    default:       return 'text-gray-400 bg-gray-500/10'
  }
}

function exportCsv() {
  const header = 'question,answer,source_timecode,difficulty\n'
  const rows = props.flashcards.map(card =>
    `"${card.question.replace(/"/g, '""')}","${card.answer.replace(/"/g, '""')}","${card.source_timecode}","${card.difficulty}"`
  ).join('\n')
  const csv = header + rows

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'flashcards-anki.csv'
  link.click()
  URL.revokeObjectURL(url)
}
</script>
