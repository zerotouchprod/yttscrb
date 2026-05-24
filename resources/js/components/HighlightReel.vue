<template>
  <div v-if="highlights.length" class="space-y-3">
    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
      <svg class="w-3.5 h-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
      </svg>
      🔥 Best Moments
    </h3>

    <div class="space-y-2">
      <div
        v-for="(moment, index) in highlights"
        :key="index"
        class="flex items-start gap-3 p-3 transition-colors border border-gray-700 rounded-lg bg-gray-800/50 hover:border-amber-500/50 group"
      >
        <!-- Category icon -->
        <div class="flex items-center justify-center w-8 h-8 mt-0.5 rounded-full shrink-0"
             :class="categoryBg(moment.category)">
          <span class="text-base">{{ categoryEmoji(moment.category) }}</span>
        </div>

        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium text-gray-200 group-hover:text-white transition-colors">
            {{ moment.title }}
          </p>
          <p class="mt-1 text-xs text-gray-400 leading-relaxed">
            {{ moment.why_notable }}
          </p>
          <div class="flex items-center gap-3 mt-2">
            <span class="text-xs text-amber-400 font-mono">{{ moment.timecode }}</span>
            <button
              @click="shareMoment(moment)"
              class="flex items-center gap-1 text-xs text-gray-500 hover:text-amber-400 transition-colors"
            >
              <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
              </svg>
              Share
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  highlights: { type: Array, required: true },
  shareUrl: { type: String, default: '' },
})

function categoryEmoji(category) {
  switch (category) {
    case 'surprise':   return '😲'
    case 'insight':    return '💡'
    case 'humor':      return '😂'
    case 'revelation': return '🤯'
    case 'quote':      return '💬'
    default:           return '⭐'
  }
}

function categoryBg(category) {
  switch (category) {
    case 'surprise':   return 'bg-amber-500/10'
    case 'insight':    return 'bg-blue-500/10'
    case 'humor':      return 'bg-green-500/10'
    case 'revelation': return 'bg-purple-500/10'
    case 'quote':      return 'bg-pink-500/10'
    default:           return 'bg-gray-500/10'
  }
}

function shareMoment(moment) {
  const publicUrl = props.shareUrl || window.location.href
  const text = `🔥 "${moment.title}" — ${moment.why_notable}\n\n📺 Full analysis: ${publicUrl}`
  const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}`
  window.open(url, '_blank', 'noopener,noreferrer')
}
</script>
