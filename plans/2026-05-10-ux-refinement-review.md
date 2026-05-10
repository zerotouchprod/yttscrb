# UX Refinement Review — 5 Product-Grade Improvements

> **Дата:** 2026-05-10
> **Статус:** Approved for immediate implementation
> **Ссылки:** [`Prd.md`](../Prd.md), [`AGENTS.md`](../AGENTS.md),
> [`2026-05-10-ux-improvements-plan.md`](2026-05-10-ux-improvements-plan.md)

---

## Ревью существующего плана

### Что уже реализовано из `2026-05-10-ux-improvements-plan.md`

| Задача | Статус | Комментарий |
|---|---|---|
| A1. Табы Summary / Transcript | ✅ Готово | `activeTab` ref, `role="tablist"`, `aria-selected` |
| A2. Thumbnail-превью из `video_id` | ⚠️ Частично | Реализовано как **full-width** (`w-full aspect-video`). По запросу нужна горизонтальная компоновка |
| A3. Иерархия кнопок Copy/Download | ✅ Готово | `copySummaryLabel` / `copyTranscriptLabel` разнесены |
| B1. Markdown в Summary | ✅ Готово | `marked` + `DOMPurify` + `prose prose-invert prose-sm` + `@tailwindcss/typography` |
| B1. Промпт Markdown в OpenAiSummaryAdapter | ✅ Готово | Промпт уже возвращает `## Key Points`, `**bold**`, `-` bullets |
| B2. Video Title | ❌ Backlog | Entity/Repo/Activity/Controller — Спринт 3 |
| C. Timecoded Transcript | ❌ Phase 2 / YAGNI | Требует backend migration + Groq `verbose_json` |

---

## 5 пользовательских улучшений — Анализ и Gap

### Improvement 1 — Горизонтальная компоновка превью (Gap 🔴)

**Проблема:** Текущий thumbnail: `w-full rounded-lg aspect-video object-cover` — занимает ~40% вертикального пространства.
Саммари «уезжает» вниз, первый экран тратится на обложку.

**Решение:** Flex-строка: thumbnail 160px (фиксированная ширина) слева + `<h2>` заголовок и URL справа.

**Зависимости:** Нет бэкенда. Чисто `App.vue`. `task.title` будет `null` до Спринта 3 — показывать URL как fallback (уже предусмотрено логикой А2).

**Риски:** Нет.

---

### Improvement 2 — Форматирование текста в Summary (Gap 🟢 DONE)

**Проблема:** "стена текста" в Summary.

**Текущее состояние:**
- ✅ `OpenAiSummaryAdapter.php` — промпт уже требует Markdown (`## Key Points`, `**bold**`, `-` bullets)
- ✅ `App.vue` — `renderedSummary = DOMPurify.sanitize(marked.parse(raw))`
- ✅ `app.css` — `@plugin "@tailwindcss/typography"`
- ✅ Summary panel — `<div v-html="renderedSummary" class="prose prose-invert prose-sm max-w-none text-gray-300">`

**Вывод:** Improvement 2 **полностью реализован**. Никаких изменений не требуется.
> ⚠️ Качество визуала зависит от того, возвращает ли реальный GPT-4o-mini Markdown. Промпт настраивает это. В dev нельзя проверить без OPENAI_API_KEY.

---

### Improvement 3 — Динамические статусы на экране загрузки (Gap 🔴)

**Проблема:** Shimmer + `"Transcribing your video..."` — статичная надпись 90 секунд.

**Решение:** Трекинг времени с момента начала polling + computed `processingStep()`:

```
0–10s  → 📥 Downloading audio track...
10–50s → 🤖 Transcribing speech with AI...
50–75s → ✨ Generating smart summary...
75s+   → ⏳ Almost done, finalizing...
```

**Реализация:**
- `processingStartedAt: ref<number|null>(null)` — `Date.now()` в момент перехода в `processing`
- `processingElapsed: ref<number>(0)` — обновляется каждую секунду через `setInterval`
- `elapsedTimer` — отдельный таймер, независимый от poll-таймера
- Остановить `elapsedTimer` при `completed` / `failed`
- В template: заменить статичный текст на `{{ processingStep.icon }} {{ processingStep.text }}`
- Добавить прогресс-бар (ширина = `Math.min(elapsed / 90 * 100, 95)%`)

**AGENTS.md compliance:** Чисто фронтенд, нет бэкенд-зависимостей. Не меняет API-контракт.

---

### Improvement 4 — Транскрипт с разбивкой на абзацы (Gap 🟡 Partial)

**Проблема:** 10 000+ слов сплошным потоком — невозможно читать.

**Текущее состояние от плана:**
- `TranscriptWord` VO существует в Domain (готовность архитектуры)
- В `CompletedResponsePlan` помечено как `Phase 2 / YAGNI` — до явного запроса

**Пользователь явно запросил** → реализуем.

**Два уровня реализации:**

#### Уровень 1 (реализуем сейчас — Frontend-only, нет backend-зависимости):
Разбиваем plain text на абзацы по `~80 слов` (computed `groupedTranscript`).
Таймкодов нет, но текст становится читабельным. Форматирование:
```html
<div v-for="(chunk, i) in groupedTranscript" :key="i" class="mb-3 leading-relaxed text-gray-300 text-sm">
  {{ chunk }}
</div>
```

#### Уровень 2 (Phase 2 — требует Backend):

| Шаг | Файл | Изменение |
|---|---|---|
| Migration | `database/migrations/` | `ALTER TABLE media_tasks ADD COLUMN result_segments JSONB NULL` |
| `GroqWhisperAdapter` | Infrastructure | Запрашивать `response_format: verbose_json`; парсить `segments[].{start,end,text}` |
| `SubtitleExtractorActivity` | Infrastructure | VTT-парсинг → массив `{start, end, text}` |
| `MediaTask` entity | Domain | `?array $segments = null` + геттер + `setSegments()` |
| `MediaTaskEloquentRepository` | Infrastructure | Маппинг `segments` в JSON |
| `TranscribeVideoController` | Infrastructure | `segments: [{start, end, text}]` в status response |
| Frontend | `App.vue` | Роутинг `segment.start` → `[MM:SS]` timecode badge → `window.open(ytUrl + &t=N)` |

**Решение на сейчас:** Уровень 1 (frontend paragraph chunking). Уровень 2 — по отдельному запросу.

---

### Improvement 5 — Визуальные акценты (Gap 🔴)

#### 5a. Кнопка Transcribe — всегда синяя (не серая в покое)

**Проблема:** `:disabled="isLoading || !youtubeUrl.trim()"` + `disabled:from-gray-600 disabled:to-gray-600` → серая кнопка.

**Решение:**
- Убрать `!youtubeUrl.trim()` из `:disabled` — кнопка всегда кликабельна визуально
- В `submitUrl()` уже есть inline-валидация `isValidYouTubeUrl()` с `urlValidationError`
- Добавить проверку пустого поля: если `youtubeUrl.trim() === ''` → `urlValidationError = 'Please paste a YouTube URL'`
- Убрать `disabled:from-gray-600 disabled:to-gray-600` из кнопки

**Результат:** Кнопка всегда синяя. При клике с пустым полем → подсвечивается ошибка под инпутом.

#### 5b. Фокус инпута — более заметное кольцо

**Текущее:** `focus:ring-2 focus:ring-blue-500/50` — полупрозрачное кольцо.
**Решение:** `focus:ring-2 focus:ring-blue-500` (убрать `/50`).

#### 5c. Value Pills — бейджи, не кнопки

**Текущее:** `border border-gray-700/50` — смотрится как outline-кнопка.
**Решение:** убрать `border border-gray-700/50`, уменьшить непрозрачность фона:
```
bg-gray-800/80 text-gray-300 border border-gray-700/50  →  bg-gray-700/30 text-gray-500
```

---

## Матрица реализации (обновлённая)

| # | Улучшение | Frontend | Backend | Время | Приоритет |
|---|---|---|---|---|---|
| 1 | Горизонтальный thumbnail | App.vue (20 строк) | ❌ нет | 30 мин | 🔴 HIGH |
| 2 | Markdown Summary | ✅ уже готово | ✅ уже готово | 0 | — |
| 3 | Динамические шаги загрузки | App.vue (40 строк) | ❌ нет | 1 час | 🔴 HIGH |
| 4 | Абзацы транскрипта (L1) | App.vue computed | ❌ нет | 30 мин | 🟡 MEDIUM |
| 4 | Timecodes (L2) | App.vue + transit | Backend + migration | 2+ дня | 🔵 PHASE 2 |
| 5 | Кнопка синяя + pills | App.vue (10 строк) | ❌ нет | 15 мин | 🟡 MEDIUM |

**Общее время реализации Phase 1 (сейчас):** ~2–3 часа, только `App.vue`.

---

## AGENTS.md Compliance

| Правило | Статус |
|---|---|
| Нет изменений бэкенд-контрактов | ✅ Phase 1 — только фронтенд |
| Нет Laravel Facades | ✅ не затронуто |
| YAGNI — timecodes | ✅ L2 явно запрошен, L1 реализуем сейчас |
| PRD: `estimated_completion_sec` в обработке | ✅ сохраняем, дополняем step-label |
| TDD: фронтенд-тесты | ⚠️ Vue component tests вне scope текущего setup (vitest не сконфигурирован) |

