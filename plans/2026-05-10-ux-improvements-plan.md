# UX Improvements Plan — Video Preview, Tabs, Markdown, Timecodes

> **Дата:** 2026-05-10
> **Статус:** In Progress — см. [`2026-05-10-ux-refinement-review.md`](2026-05-10-ux-refinement-review.md) для Phase 2 review
> **Ссылки:** [`Prd.md`](../Prd.md), [`AGENTS.md`](../AGENTS.md), [`App.vue`](../resources/js/App.vue)

---

## Анализ текущего состояния

### 1. Фронтенд — `resources/js/App.vue`

Единственный компонент (~294 строки). Текущая структура Completed-состояния:

```
[Status Badge Row]
[Summary block — plain text, border-left highlight]
[Transcript block — plain text, max-h-96 scroll]
  [Copy (small link) | word_count]
[Download TXT (large green button)]
```

**Проблемы:**
- Нет превью видео (thumbnail + title) после Completed-статуса
- Нет системы табов — Summary и Transcript в виде стека, скролл длинный
- Summary рендерится как `{{ task.result.summary }}` — Markdown не поддерживается
- Транскрипт — сплошной текст без таймкодов (`whitespace-pre-wrap`)
- Copy — мелкая ссылка в шапке блока, не является primary CTA
- Download — крупная зелёная кнопка, визуально не связана с Summary-табом

---

### 2. Backend — API Contract

**`GET /api/transcribe/{id}` (completed) — текущий ответ:**
```json
{
  "task_id": "...",
  "status": "completed",
  "youtube_url": "...",
  "video_id": "dQw4w9WgXcQ",
  "title": null,
  "duration_sec": 212,
  "result": {
    "transcript": "...",
    "summary": "...",
    "word_count": 283
  }
}
```

**Вывод:**
- `thumbnail_url` не нужен в API — вычисляется на фронтенде из `video_id`:
  `https://img.youtube.com/vi/{video_id}/maxresdefault.jpg`
- `video_id` ✅ есть в status response — достаточно для thumbnail
- `title` — колонка в БД есть, но **не заполняется** (workflow не извлекает заголовок), Entity не имеет поля, контроллер хардкодит `null`

---

### 3. Backend — `MediaTask` Entity

```
Текущие поля: id, youtubeUrl, createdAt, status, workflowId, resultText,
              summary, errorMessage, completedAt, failedAt, durationSec
❌ НЕТ: title, thumbnailUrl, resultVtt
```

---

### 4. Backend — `OpenAiSummaryAdapter`

Текущий промпт возвращает plain text без форматирования. Groq API поддерживает `verbose_json` с сегментами (start/end/text), но используется простой `text` формат. `TranscriptWord` VO существует в Domain, но сейчас не используется в адаптере.

---

### 5. Frontend Dependencies

`package.json` содержит только `axios` и `vue`. Для Markdown нужно добавить:
- `marked` (парсер Markdown)
- `dompurify` (санитизация HTML перед `v-html`)
- `@tailwindcss/typography` (prose классы для dark mode)

---

## Изменения по файлам и порядок реализации

---

### 🟢 Спринт 1 (2–3 дня) — Pure Frontend (нет изменений бэкенда)

#### A1. Табы Summary / Transcript (сложность: **Низкая**)

**Файл:** `resources/js/App.vue`

- Добавить `activeTab` ref (`'summary'` по умолчанию)
- Таб-переключатель вверху Completed-карточки:
  ```
  [ ✨ AI Summary ] | [ 📝 Full Transcript ]
  ```
- Активный таб: синий фон / accent border; неактивный: ghost
- Условный рендеринг: `v-show="activeTab === 'summary'"` / `v-show="activeTab === 'transcript'"`
- Сбрасывать `activeTab` в `'summary'` при новом запросе (`submitUrl`)

#### A2. Видео-превью из `video_id` (сложность: **Низкая**)

**Файл:** `resources/js/App.vue`

- `thumbnailUrl` — computed свойство:
  ```js
  const thumbnailUrl = computed(() =>
    task.value?.video_id
      ? `https://img.youtube.com/vi/${task.value.video_id}/maxresdefault.jpg`
      : null
  )
  ```
- Вставить блок превью **сразу после Status Badge Row** при `task.status === 'completed'`:
  ```html
  <img :src="thumbnailUrl" @error="thumbnailError = true" class="rounded-lg aspect-video object-cover" />
  <p>{{ task.title || task.youtube_url }}</p>
  ```
- `@error`: скрывать блок, если картинка недоступна (`thumbnailError = true`)
- `task.title` будет `null` до реализации бэкенда (Спринт 3) — показывать URL как fallback

#### A3. Иерархия кнопок Copy/Download (сложность: **Низкая**)

**Файл:** `resources/js/App.vue`

- **Summary-таб:** кнопка `[ 📋 Copy Summary ]` (primary: `bg-blue-600`, полная ширина на mobile) в нижней части блока
- **Transcript-таб:**
  - `[ 📋 Copy Transcript ]` — primary, большая
  - `[ ⬇️ Download .txt ]` — secondary/outline (`border border-gray-600 hover:border-gray-400`), рядом
- Удалить старый inline-link Copy из заголовка Transcript-блока
- Разбить `copyLabel` на `copySummaryLabel` и `copyTranscriptLabel` для независимых состояний "Copied!"

---

### 🟡 Спринт 2 (2–3 дня) — Markdown в Summary

#### B1. Markdown-рендеринг (сложность: **Средняя**)

**Файл 1:** `package.json`
```bash
npm install marked dompurify
npm install -D @tailwindcss/typography
```

**Файл 2:** `resources/js/App.vue`
- Импортировать `marked` и `DOMPurify`:
  ```js
  import { marked } from 'marked'
  import DOMPurify from 'dompurify'
  ```
- Computed:
  ```js
  const renderedSummary = computed(() =>
    DOMPurify.sanitize(marked.parse(task.value?.result?.summary ?? ''))
  )
  ```
- В шаблоне заменить `{{ task.result.summary }}` на:
  ```html
  <div v-html="renderedSummary" class="prose prose-invert prose-sm max-w-none"></div>
  ```
- Добавить `@tailwindcss/typography` в `tailwind.config.js` → `plugins: [require('@tailwindcss/typography')]`

**Файл 3:** `app/Infrastructure/Adapters/Output/Summary/OpenAiSummaryAdapter.php`
- Обновить промпт для запроса Markdown-вывода:
  ```
  You are a helpful assistant that summarizes video transcripts.
  Return the summary in Markdown format with:
  - A brief intro paragraph
  - **Bold key takeaways** as bullet points under a "## Key Points" header
  - Additional ## Section headers if the content has distinct topics
  Use concise language. Maximum {maxWords} words. Style: {style}.

  Transcript:
  {transcript}
  ```

**Тесты (обязательно):**
- Обновить контрактный тест `OpenAiSummaryAdapterTest` — mock ответ с Markdown (`##`, `**`, `-`)
- Проверить что `SummaryResult::text()` передаёт строку без изменений

---

### 🟠 Спринт 3 (3–4 дня) — Video Title (полная цепочка)

#### B2.1 Domain — `app/Domain/Entities/MediaTask.php`

- Добавить `private ?string $title = null`
- Добавить геттер `public function title(): ?string`
- Добавить метод `public function setTitle(string $title): void` (не ломает сигнатуру `complete()`)
- **Тесты:** обновить `tests/Unit/Domain/Entities/MediaTaskTest.php` — 100% покрытие обязательно (unit тест `setTitle` / `title()`)

#### B2.2 Infrastructure — Repository

**Файл:** `app/Infrastructure/Adapters/Output/Persistence/MediaTaskEloquentRepository.php`

- В `toEntity()`: добавить маппинг `title` → `$task->setTitle($model->title)` (если не null)
- В `toArray()`: добавить `'title' => $task->title()`
- Обновить тест репозитория

#### B2.3 Infrastructure — Workflow Activity

Вариант A (рекомендованный — SRP): новый `VideoMetadataActivity`
- **Файл:** `app/Infrastructure/Workflow/Activities/VideoMetadataActivity.php`
- Вызывает `yt-dlp --dump-json {url}` → парсит `title` из JSON
- Обновить `TranscribeVideoWorkflow` — шаг после `SubtitleExtractorActivity`, передать `title` в `PersistResultActivity`
- Зарегистрировать в DI

Вариант B (упрощённый — YAGNI для v1.0): расширить `SubtitleExtractorActivity`
- Добавить `--print title` или парсинг из `--dump-json`; вернуть title в payload активности

> **Решение:** для v1.0 использовать Вариант B — меньше изменений, тот же `yt-dlp` вызов. При необходимости рефакторить в отдельную Activity.

**Тесты:** контрактный тест измененной активности с mocked process output

#### B2.4 Infrastructure — Controller

**Файл:** `app/Infrastructure/Adapters/Input/Web/TranscribeVideoController.php`

- В `status()`, `history()`, `latest()`: заменить `'title' => null` на `'title' => $task->title()`
- Обновить feature tests: completed response содержит `title` (не null для завершённых задач)

#### B2.5 Frontend

**Файл:** `resources/js/App.vue`

- Использовать `task.title` (когда не null) в превью-блоке вместо URL-fallback (автоматически работает после реализации бэкенда через ternary из A2)

---

### 🔴 Спринт 4 (5–7 дней) — Таймкоды в транскрипте [Phase 2]

> **YAGNI-предупреждение:** Реализовывать только по явному запросу. Высокая сложность, требует schema migration.

#### C1. Текущая цепочка

```
GroqTranscriberActivity → TranscriptionResult → complete(transcript.text()) → result_text (plain)
                                      ↑
                            TranscriptWord VO (word, start, end) существует в Domain,
                            но не используется в адаптере — сегменты отбрасываются
```

#### C2. Backend изменения

**C2.1 Migration** — `result_vtt TEXT NULL` в `media_tasks` (VTT с таймингами)

**C2.2 GroqWhisperAdapter**
- Запрашивать `response_format: verbose_json` от Groq API (возвращает `segments[].{start, end, text}`)
- Сохранять сегменты как JSON в `result_vtt`, plain text в `result_text` (без изменений для download/word_count)

**C2.3 SubtitleExtractorActivity**
- yt-dlp `--write-auto-sub` возвращает `.vtt` — уже содержит таймкоды
- Сохранять VTT как-есть в `result_vtt`

**C2.4 MediaTask Entity**
- Добавить `?string $resultVtt = null` поле + геттер + `setResultVtt()`
- **Тесты:** 100% unit покрытие нового поля

**C2.5 Repository** — маппинг `result_vtt` в `toEntity()` и `toArray()`

**C2.6 Controller** — добавить `segments: [{start, end, text}]` в status response для completed задач

#### C3. Frontend — `TranscriptViewer.vue` (новый компонент)

- Парсинг `segments[]` из API ответа
- Computed: группировка по параграфам (каждые N секунд / по паузам)
- Форматирование `[MM:SS]` из `segment.start`
- Рендер:
  ```html
  <div v-for="segment in parsedSegments" :key="segment.start">
    <button class="timecode text-blue-400" @click="openAtTime(segment.start)">
      [{{ formatTime(segment.start) }}]
    </button>
    <span>{{ segment.text }}</span>
  </div>
  ```
- `openAtTime(seconds)`: `window.open(\`https://youtube.com/watch?v=${videoId}&t=${Math.floor(seconds)}\`)`
- Fallback: если `segments` пустой → рендер plain text как сейчас

---

## Матрица сложности и трудозатрат

| Фича | Сложность | Backend | Frontend | Новые тесты | Время |
|---|---|---|---|---|---|
| A1. Табы | ⭐ Низкая | Нет | App.vue | Нет | 0.5 дня |
| A2. Thumbnail-превью | ⭐ Низкая | Нет | App.vue | Нет | 0.5 дня |
| A3. Кнопки Copy/Download | ⭐ Низкая | Нет | App.vue | Нет | 0.5 дня |
| B1. Markdown Summary | ⭐⭐ Средняя | OpenAiSummaryAdapter | App.vue + пакеты | Контрактный тест | 1.5 дня |
| B2. Video Title | ⭐⭐⭐ Высокая | Entity+Repo+Activity+Controller | App.vue | Unit+Feature+Contract | 3 дня |
| C. Timecoded Transcript | ⭐⭐⭐⭐ Очень высокая | Migration+Groq+Sub+Entity+Repo+Ctrl | TranscriptViewer.vue | Все слои | 6 дней |

---

## AGENTS.md Compliance Check

| Правило | Затронутые задачи | Действие |
|---|---|---|
| PHPStan level 9 | B1, B2, C | Запускать после каждого PHP-изменения; nullable типы требуют strict аннотаций |
| Deptrac: Domain→Infrastructure запрещено | B2, C (entity поля) | ✅ только примитивные типы в Domain |
| TDD: Domain/Application 100% | B2.1, C2.4 | Писать тесты ДО кода |
| No Laravel Facades | B2.3 (Activity) | Использовать Process через DI, не `Process::run()` фасад |
| YAGNI | C (timecodes) | Phase 2, не реализовывать до явного запроса |
| PSR-12 | Все PHP | phpcs проверит автоматически |
| No provider-specific в Domain/Application | B2 (title activity) | Activity в Infrastructure, не в Application |
| Secrets не хардкодить | B2.3 (yt-dlp) | URL через DI, не захардкожен |

---

## Ключевые архитектурные решения

1. **thumbnail_url НЕ нужен в API** — дерайвится из `video_id` через YouTube CDN на фронтенде. Нет изменений в бэкенде.

2. **title: Вариант B** — расширить `SubtitleExtractorActivity` для v1.0 (YAGNI). При росте complexity → рефакторинг в `VideoMetadataActivity`.

3. **Markdown в Summary** — промпт изменяется только в Infrastructure-адаптере. Domain/Application не знают о Markdown.

4. **Timecodes — Phase 2** — `TranscriptWord` VO в Domain уже есть, это архитектурная готовность. Production-код не трогаем до запроса.

5. **Хранение VTT** — отдельная колонка `result_vtt` (не ломает `result_text` для download/word_count).

