<template>
  <div v-if="blogPost" class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
        <svg class="w-3.5 h-3.5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
        </svg>
        Blog Post
      </h3>
      <div class="flex items-center gap-2">
        <button
          @click="copyMarkdown"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-green-400 transition-colors border border-green-500/30 rounded-lg bg-green-500/10 hover:bg-green-500/20"
          :title="copyMdLabel"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-2m-4 6h.01"/>
          </svg>
          {{ copyMdLabel }}
        </button>
        <button
          @click="copyHtml"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-400 transition-colors border border-blue-500/30 rounded-lg bg-blue-500/10 hover:bg-blue-500/20"
          :title="copyHtmlLabel"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
          </svg>
          {{ copyHtmlLabel }}
        </button>
        <button
          @click="downloadMd"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-purple-400 transition-colors border border-purple-500/30 rounded-lg bg-purple-500/10 hover:bg-purple-500/20"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Download .md
        </button>
      </div>
    </div>

    <!-- Preview pane -->
    <div class="bg-gray-800/60 rounded-xl p-5 border border-gray-700 space-y-4 max-h-96 overflow-y-auto">
      <h1 class="text-xl font-bold text-white leading-tight">
        {{ blogPost.title }}
      </h1>
      <div
        v-for="(section, index) in blogPost.sections"
        :key="index"
        class="border-t border-gray-700/50 pt-4 first:border-0 first:pt-0"
      >
        <h2 class="text-base font-semibold text-gray-200 mb-2">
          {{ section.heading }}
        </h2>
        <p class="text-sm text-gray-400 leading-relaxed whitespace-pre-line">
          {{ section.body }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  blogPost: { type: Object, default: null },
})

const copyMdLabel = ref('Copy Markdown')
const copyHtmlLabel = ref('Copy HTML')

function buildMarkdown() {
  if (!props.blogPost) return ''
  let md = `# ${props.blogPost.title}\n\n`
  for (const section of props.blogPost.sections) {
    md += `## ${section.heading}\n\n${section.body}\n\n`
  }
  return md.trim()
}

function buildHtml() {
  if (!props.blogPost) return ''
  let html = `<h1>${escapeHtml(props.blogPost.title)}</h1>\n`
  for (const section of props.blogPost.sections) {
    html += `<h2>${escapeHtml(section.heading)}</h2>\n`
    html += `<p>${escapeHtml(section.body)}</p>\n`
  }
  return html.trim()
}

function escapeHtml(text) {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

async function copyMarkdown() {
  try {
    await navigator.clipboard.writeText(buildMarkdown())
    copyMdLabel.value = 'Copied!'
    setTimeout(() => { copyMdLabel.value = 'Copy Markdown' }, 2000)
  } catch {
    copyMdLabel.value = 'Failed'
    setTimeout(() => { copyMdLabel.value = 'Copy Markdown' }, 2000)
  }
}

async function copyHtml() {
  try {
    await navigator.clipboard.writeText(buildHtml())
    copyHtmlLabel.value = 'Copied!'
    setTimeout(() => { copyHtmlLabel.value = 'Copy HTML' }, 2000)
  } catch {
    copyHtmlLabel.value = 'Failed'
    setTimeout(() => { copyHtmlLabel.value = 'Copy HTML' }, 2000)
  }
}

function downloadMd() {
  const content = buildMarkdown()
  const slug = slugify(props.blogPost?.title ?? 'blog-post')
  const blob = new Blob([content], { type: 'text/markdown;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${slug}.md`
  link.click()
  URL.revokeObjectURL(url)
}

function slugify(text) {
  return text
    .toString()
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^\w-]+/g, '')
    .replace(/--+/g, '-')
    .replace(/^-+/, '')
    .replace(/-+$/, '')
    .substring(0, 60)
}
</script>
