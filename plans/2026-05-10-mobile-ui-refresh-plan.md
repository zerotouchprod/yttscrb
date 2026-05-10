# Mobile UI Refresh — Implementation Plan

## Overview

Исправление мобильной вёрстки и визуальное улучшение главного экрана TubeSum без изменения продуктовой логики, API-контрактов или структуры данных.

**Спецификация:** [`docs/superpowers/specs/2026-05-10-mobile-ui-refresh-design.md`](../docs/superpowers/specs/2026-05-10-mobile-ui-refresh-design.md)

**Стек:** Tailwind CSS v4, Vue 3 Composition API, Laravel Vite

**Файлы для изменения:**
- `resources/js/App.vue` — основной компонент (template + script + style)
- `resources/css/app.css` — декоративные глобальные стили

**Вне scope:**
- Изменения API, workflow, статусов задач
- Новые страницы (история, авторизация)
- Настройка vitest + vue-test-utils
- Маркетинговые секции

---

## Phase 1: Mobile Layout Fix (Критический приоритет)

### 1.1 Форма — вертикальный stacking на мобильных

**Текущее:**
```html
<form @submit.prevent="submitUrl" class="flex gap-3">
```

**Целевое:**
```html
<form @submit.prevent="submitUrl" class="flex flex-col sm:flex-row gap-3">
```

### 1.2 Input — полная ширина + предотвращение overflow

- Добавить `w-full` на input
- Добавить `min-w-0` для предотвращения overflow с длинными URL
- Добавить `break-all` для placeholder на мобильных

### 1.3 Button — полная ширина на мобильных

- `w-full sm:w-auto`
- Сохранить disabled-состояние

### 1.4 Все карточки — max-w-full

- Добавить `max-w-full overflow-hidden` где нужно
- Транскрипт: `break-words` к текстовым блокам

### 1.5 Main container padding

- `px-4 sm:px-6 lg:px-8` для адаптивных отступов

---

## Phase 2: Hero Upgrade

### 2.1 Декоративный градиентный фон

В `app.css` добавить:
```css
@utility hero-glow {
  background: radial-gradient(ellipse at 50% 0%, rgba(59, 130, 246, 0.15), transparent 70%);
}
```

### 2.2 Типографика

- Заголовок: `text-5xl sm:text-6xl font-bold tracking-tight` + градиент текста `bg-gradient-to-r from-blue-400 to-blue-200 bg-clip-text text-transparent`
- Подзаголовок: `text-lg sm:text-xl text-gray-400`

### 2.3 Value Pills

Три компактных бейджа под подзаголовком:
- "No Signup" — иконка замка
- "AI Summary" — иконка sparkle
- "Full Transcript" — иконка документа

Размещение: `flex flex-wrap justify-center gap-2 mt-4`

---

## Phase 3: Form Card Glass Morphism

### 3.1 Glass-эффект

- `bg-gray-800/80 backdrop-blur-sm border border-gray-700/50 shadow-xl`
- Вместо сплошного `bg-gray-800 border-gray-700`

### 3.2 Input focus state

- `focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500`
- `transition-all duration-200` для плавности

### 3.3 CTA Button

- Градиент: `bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400`
- Тень: `shadow-lg shadow-blue-500/20`
- `transition-all duration-200`

---

## Phase 4: Status & Result Card

### 4.1 Status Badges с иконками

Добавить inline SVG рядом с текстом статуса:
- `pending` — clock icon (желтый)
- `processing` — spinner (синий)
- `completed` — check-circle (зеленый)
- `failed` — x-circle (красный)

### 4.2 Skeleton Loading (Shimmer)

Заменить `animate-pulse` блоки на shimmer effect:
```html
<div class="animate-shimmer bg-gradient-to-r from-gray-700 via-gray-600 to-gray-700 bg-[length:200%_100%] ..."></div>
```

Добавить в `app.css`:
```css
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
@utility animate-shimmer {
  animation: shimmer 1.5s infinite;
}
```

### 4.3 Summary Block

- Выделить как featured: `bg-gray-700/60 border-l-4 border-blue-500 rounded-r-lg`
- Иконка sparkle рядом с заголовком "Summary"

### 4.4 Transcript Block

- Сохранить `max-h-96 overflow-y-auto`
- Добавить кнопку "Copy" рядом со счётчиком слов
- `navigator.clipboard.writeText()` + tooltip "Copied!"

### 4.5 Download Button

- `w-full sm:w-auto`
- Иконка + текст
- Улучшенный hover/active state

### 4.6 Failed State

- Заменить текстовый `!` на SVG-иконку (exclamation-circle)
- Retry button: `w-full sm:w-auto`, улучшенный стиль

---

## Phase 5: Accessibility

### 5.1 ARIA-атрибуты

| Элемент | Атрибут |
|---------|---------|
| Status badge | `role="status"` |
| Result container | `aria-live="polite"` |
| Download button | `aria-label="Download transcript as TXT"` |
| Copy button | `aria-label="Copy transcript to clipboard"` |
| Error card | `role="alert"` |
| Form input | `aria-label="YouTube video URL"` |
| Loading spinner | `aria-busy="true"` |

### 5.2 Focus Management

- После отправки формы — фокус на status card (или сохранить на форме для повторного ввода)
- После ошибки — фокус на error card

---

## Phase 6: Client-side URL Validation

### 6.1 Валидация перед submitUrl()

```ts
function isValidYouTubeUrl(url: string): boolean {
  const patterns = [
    /^https?:\/\/(www\.)?youtube\.com\/watch\?v=[\w-]+/,
    /^https?:\/\/(www\.)?youtu\.be\/[\w-]+/,
    /^https?:\/\/(m\.)?youtube\.com\/watch\?v=[\w-]+/,
  ];
  return patterns.some(p => p.test(url));
}
```

### 6.2 Inline Validation Error

- Показывать ошибку валидации под input (до отправки)
- Не блокировать кнопку, но показывать предупреждение
- Ошибка API (от бэкенда) по-прежнему обрабатывается отдельно

---

## Phase 7: Verification

### 7.1 Сборка

```bash
npm run build
```

Ожидается успешная сборка без ошибок.

### 7.2 Ручная проверка (Manual QA)

| Breakpoint | Ширина | Проверка |
|-----------|--------|----------|
| Mobile S | 375px | Форма вертикальная, нет горизонтального скролла, кнопки full-width |
| Mobile L | 428px | То же самое |
| Tablet | 768px | Форма горизонтальная на sm+, карточки центрированы |
| Desktop | 1280px | Полный layout, max-w-3xl контейнер |

### 7.3 Проверка состояний

- [ ] `pending` — badge + skeleton/skeleton placeholder
- [ ] `processing` — badge + spinner + estimated time
- [ ] `completed` — summary + transcript + word count + copy + download
- [ ] `failed` — error message + retry button

### 7.4 Крайние случаи

- [ ] Длинный YouTube URL (с параметрами `&list=...&index=...`)
- [ ] Длинный транскрипт (10K+ слов) — scroll внутри контейнера
- [ ] Короткий summary (одно предложение)
- [ ] Отсутствующий summary (null)
- [ ] Пустой error_message в failed состоянии

---

## Порядок реализации

```
Phase 1 ──► Phase 2 ──► Phase 3 ──► Phase 4 ──► Phase 5 ──► Phase 6 ──► Phase 7
(mobile)   (hero)     (form)      (result)    (a11y)      (validation) (verify)
```

Phases 1-4 — визуальные изменения (основной объём).
Phase 5 — accessibility (низкий риск).
Phase 6 — client-side validation (новый функционал, но чисто фронтенд).
Phase 7 — финальная проверка.

---

## Phase 8: Analytics (Yandex Metrika)

### 8.1 Добавление счётчика

В [`welcome.blade.php`](resources/views/welcome.blade.php) перед `</body>`:

- Yandex Metrika counter (id: `109136117`) с Webvisor, Clickmap, Ecommerce
- `<noscript>` fallback с пикселем
- Параметры: `accurateTrackBounce:true`, `trackLinks:true`, `ssr:true`

### 8.2 Применение

Файл уже изменён — счётчик добавлен.

---

## Phase 9: SEO Optimization

### 9.1 Meta-теги

В [`welcome.blade.php`](resources/views/welcome.blade.php) в `<head>`:

| Тег | Содержание |
|-----|-----------|
| `<title>` | `TubeSum — Free YouTube Transcriber & AI Summarizer \| Video to Text` |
| `description` | 155 символов: Free, no signup, AI summary, extract subtitles, transcribe audio |
| `keywords` | EN: YouTube transcriber, transcription, video to text, summarizer, AI summary, speech to text, subtitle extractor, free transcription. RU: транскрибация YouTube, расшифровка видео, суммаризация, текст из видео |
| `robots` | `index, follow` |
| `canonical` | `https://tubesum.app` |

### 9.2 Open Graph

- `og:type`, `og:title`, `og:description`, `og:url`, `og:site_name`, `og:locale`

### 9.3 Twitter Card

- `twitter:card` = `summary_large_image`, title, description

### 9.4 Structured Data (JSON-LD)

- `WebApplication` schema: name, description, url, applicationCategory, free offer, browserRequirements

### 9.5 Ключевые слова (подборка)

**Primary (EN):**
- YouTube transcriber
- YouTube transcription
- video to text
- YouTube summarizer
- AI summary

**Secondary (EN):**
- speech to text YouTube
- YouTube subtitle extractor
- free transcription tool
- YouTube audio to text
- video transcript generator

**Primary (RU):**
- транскрибация YouTube
- расшифровка видео
- текст из видео YouTube
- суммаризация видео

**Secondary (RU):**
- субтитры из YouTube
- перевести видео в текст
- конспект видео
- краткое содержание видео

### 9.6 Применение

Файл уже изменён — SEO-теги добавлены.
