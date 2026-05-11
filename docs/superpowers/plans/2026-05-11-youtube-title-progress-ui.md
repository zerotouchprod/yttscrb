# YouTube Title in Progress UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show the real YouTube title and thumbnail during pending/processing states, remove rough fallback labels, and make progress stages clearer.

**Architecture:** Keep the backend workflow as the source of truth for video metadata. The controller must consistently expose `video_id` and `title` for pending/processing/completed states, while the Vue app renders a compact preview block during progress and swaps generic fallbacks for state-aware copy. Tests stay focused on API contract stability; frontend verification is done with a production build.

**Tech Stack:** Laravel 13, PHP 8.5, Vue 3 Composition API, TailwindCSS, Pest/PHPUnit.

---

## File Map

- `app/Infrastructure/Adapters/Input/Web/TranscribeVideoController.php`
  - Ensure create response for new tasks returns `video_id` and `title` so the UI can render preview immediately.
- `resources/js/App.vue`
  - Add preview UI for pending/processing, more explicit progress stage text, and better fallback labels in recent/search/completed blocks.
- `tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php`
  - Lock the API contract for create/status/history responses around `video_id`/`title` availability.

---

### Task 1: Lock backend response contract for newly created tasks

**Files:**
- Modify: `tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php`
- Modify: `app/Infrastructure/Adapters/Input/Web/TranscribeVideoController.php`

- [ ] **Step 1: Write the failing feature assertions for create response**

Add assertions to `testGuestCanCreateATranscriptionTaskWithoutRegistration()` so the 202 payload includes the YouTube `video_id` and nullable `title`:

```php
$response->assertAccepted()
    ->assertJsonPath('status', 'pending')
    ->assertJsonPath('youtube_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
    ->assertJsonPath('video_id', 'dQw4w9WgXcQ')
    ->assertJsonPath('title', null);
```

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `php artisan test tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php --filter=testGuestCanCreateATranscriptionTaskWithoutRegistration`

Expected: FAIL because the 202 create response does not include `video_id` and `title`.

- [ ] **Step 3: Implement the minimal controller response change**

In `TranscribeVideoController::create()`, extend the 202 JSON payload:

```php
return new JsonResponse([
    'task_id' => $storedTask->id(),
    'status' => $storedTask->status()->value,
    'youtube_url' => $storedTask->youtubeUrl()->value(),
    'video_id' => $storedTask->youtubeUrl()->videoId()->value(),
    'title' => $storedTask->title(),
    'created_at' => $storedTask->createdAt()->format('c'),
    '_links' => [
        'status' => "/api/transcribe/{$storedTask->id()}",
    ],
], Response::HTTP_ACCEPTED);
```

- [ ] **Step 4: Re-run the focused feature test**

Run: `php artisan test tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php --filter=testGuestCanCreateATranscriptionTaskWithoutRegistration`

Expected: PASS.

- [ ] **Step 5: Commit backend contract change**

```bash
git add app/Infrastructure/Adapters/Input/Web/TranscribeVideoController.php tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php
git commit -m "feat: expose youtube metadata in pending transcription response"
```

---

### Task 2: Show preview and title state during pending/processing

**Files:**
- Modify: `resources/js/App.vue`

- [ ] **Step 1: Add state-aware helpers for preview title and progress copy**

In `<script setup>`, add computed helpers near `thumbnailUrl`, `processingStep`, and related state:

```js
const previewTitle = computed(() => {
  if (task.value?.title) return task.value.title;

  if (task.value?.status === 'pending' || task.value?.status === 'processing') {
    return 'Fetching video title...';
  }

  return 'Video title unavailable';
});

const previewSubtitle = computed(() => {
  if (task.value?.status === 'pending') return 'Queued and preparing metadata';
  if (task.value?.status === 'processing') return 'Processing video and updating details';
  return task.value?.youtube_url || '';
});
```

Update the `processingStep` computed so the labels feel like real stages:

```js
const processingStep = computed(() => {
  const elapsed = processingElapsed.value;

  if (elapsed < 10) return { icon: '🛈', text: 'Checking subtitles and fetching video title...' };
  if (elapsed < 35) return { icon: '🎧', text: 'Transcribing audio...' };
  if (elapsed < 65) return { icon: '✨', text: 'Generating AI summary...' };

  return { icon: '📝', text: 'Finalizing transcript and saving result...' };
});
```

- [ ] **Step 2: Add compact preview block above pending/processing content**

Inside the status card template, insert one shared preview block before the pending/processing/completed branches:

```html
<div v-if="thumbnailUrl && !thumbnailError" class="flex gap-4 items-start mb-5">
  <img
    :src="thumbnailUrl"
    @error="thumbnailError = true"
    class="w-[150px] flex-shrink-0 rounded-lg object-cover bg-gray-700"
    style="aspect-ratio: 16/9"
    :alt="previewTitle"
    loading="lazy"
  />
  <div class="min-w-0 flex-1 pt-0.5">
    <h2 class="text-sm font-semibold text-white leading-snug line-clamp-3">
      {{ previewTitle }}
    </h2>
    <p class="mt-1.5 text-xs text-gray-500 truncate">{{ task.youtube_url }}</p>
    <p class="mt-1 text-xs text-gray-600">{{ previewSubtitle }}</p>
  </div>
</div>
```

Then remove the duplicate preview block from the completed-only section so there is a single source of markup.

- [ ] **Step 3: Use better fallback copy in list/search/completed labels**

Replace the rough generic labels in `App.vue`:

```vue
{{ t.title || 'Title unavailable' }}
{{ item.title || 'Title unavailable' }}
{{ previewTitle }}
```

Do not keep `Untitled` or `YouTube Video` in the main page UI.

- [ ] **Step 4: Run the frontend production build**

Run: `npm run build`

Expected: PASS with no Vue template or compile errors.

- [ ] **Step 5: Commit the UI change**

```bash
git add resources/js/App.vue
git commit -m "feat: show youtube preview and title state during transcription"
```

---

### Task 3: Verify end-to-end contract and regression surface

**Files:**
- Modify: `tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php`

- [ ] **Step 1: Add one more assertion for history payload stability**

In `testReturnsHistoryWithoutRegistration()`, assert the history item still exposes `video_id` so the recent list can always build thumbnails:

```php
$response->assertOk()
    ->assertJsonCount(2, 'data')
    ->assertJsonPath('meta.total', 2)
    ->assertJsonPath('data.0.video_id', 'bbbbbbbbbbb');
```

If ordering is unstable in your test data, assert against the known item by reading `$response->json('data')` and checking that one entry contains the expected `video_id`.

- [ ] **Step 2: Run the focused feature file**

Run: `php artisan test tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php`

Expected: PASS.

- [ ] **Step 3: Run lightweight verification for touched areas**

Run:

```bash
php artisan test tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php
npm run build
```

Expected: both commands PASS.

- [ ] **Step 4: Commit the verification-aligned test updates**

```bash
git add tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php
git commit -m "test: cover youtube metadata for progress and history ui"
```

---

## Self-review

- Spec coverage: backend contract, progress preview, fallback copy, and clearer progress stages are all covered.
- Placeholder scan: no TBD/TODO placeholders remain.
- Type consistency: uses existing `video_id`, `title`, `task`, `thumbnailUrl`, and controller JSON structure.
