<template>
  <div v-if="linkedInPost" class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
        <svg class="w-3.5 h-3.5 text-blue-400" fill="currentColor" viewBox="0 0 24 24">
          <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
        </svg>
        LinkedIn Post
      </h3>
      <div class="flex items-center gap-2">
        <span class="text-xs text-gray-500 bg-gray-700/50 px-2 py-1 rounded-full">
          {{ charCount }} chars
        </span>
        <button
          @click="copyPost"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-400 transition-colors border border-blue-500/30 rounded-lg bg-blue-500/10 hover:bg-blue-500/20"
          :title="copyLabel"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          {{ copyLabel }}
        </button>
        <button
          @click="openLinkedIn"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-500 transition-colors border border-blue-500/40 rounded-lg bg-blue-500/10 hover:bg-blue-500/25"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
          </svg>
          Open LinkedIn
        </button>
      </div>
    </div>

    <!-- Preview pane -->
    <div class="bg-gray-800/60 rounded-xl p-5 border border-gray-700 space-y-4 max-h-96 overflow-y-auto">
      <!-- Hook -->
      <div class="border-b border-gray-700/50 pb-4">
        <p class="text-base font-semibold text-white leading-snug whitespace-pre-line">
          {{ linkedInPost.hook }}
        </p>
      </div>

      <!-- Body -->
      <div class="border-b border-gray-700/50 pb-4">
        <p class="text-sm text-gray-300 leading-relaxed whitespace-pre-line">
          {{ linkedInPost.body }}
        </p>
      </div>

      <!-- CTA -->
      <div>
        <p class="text-sm text-blue-300 font-medium">
          {{ resolvedCta }}
        </p>
      </div>
    </div>

    <p class="text-xs text-gray-500 italic">
      Paste the full post in LinkedIn after clicking "Share".
    </p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  linkedInPost: { type: Object, default: null },
  publicUrl: { type: String, default: '' },
})

const copyLabel = ref('Copy Post')

/** Total character count for the full post. */
const charCount = computed(() => {
  if (!props.linkedInPost) return 0
  return (props.linkedInPost.hook + '\n\n' + props.linkedInPost.body + '\n\n' + resolvedCta.value).length
})

/** Substitute [URL] with the real public page URL. */
const resolvedCta = computed(() => {
  if (!props.linkedInPost) return ''
  return props.linkedInPost.call_to_action.replace('[URL]', props.publicUrl || '[URL]')
})

/** Build the full post text with [URL] substitution. */
function buildPostText() {
  if (!props.linkedInPost) return ''
  return props.linkedInPost.hook + '\n\n' + props.linkedInPost.body + '\n\n' + resolvedCta.value
}

async function copyPost() {
  try {
    await navigator.clipboard.writeText(buildPostText())
    copyLabel.value = 'Copied!'
    setTimeout(() => { copyLabel.value = 'Copy Post' }, 2000)
  } catch {
    copyLabel.value = 'Failed'
    setTimeout(() => { copyLabel.value = 'Copy Post' }, 2000)
  }
}

function openLinkedIn() {
  if (!props.linkedInPost) return
  const text = encodeURIComponent(props.linkedInPost.hook + '\n\n' + props.linkedInPost.body + '\n\n' + resolvedCta.value)
  window.open(`https://www.linkedin.com/shareArticle?mini=true&text=${text}`, '_blank', 'noopener')
}
</script>
