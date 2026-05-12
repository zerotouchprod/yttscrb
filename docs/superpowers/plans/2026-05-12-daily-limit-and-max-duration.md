# Daily Limit & Max Duration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace monthly quota (10/month) with daily quota (10/day) and add 30-minute max video duration guard.

**Architecture:** Two independent checks in `TranscribeVideoController::create()`: (1) daily completed count via existing `countCompletedSince()` with today's start, (2) video duration via `SubtitleProviderInterface::extractDuration()` before saving task. Both return 429/422 with friendly messages.

**Tech Stack:** PHP 8.5, Laravel, yt-dlp (via existing adapter)

---

### Task 1: Add `countCompletedToday()` to handler

**Files:**
- Modify: `app/Application/UseCases/TranscribeVideoHandler.php`
- Test: `tests/Unit/Application/UseCases/TranscribeVideoHandlerTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('counts completed transcriptions since today midnight', function (): void {
    $repo = Mockery::mock(MediaTaskRepositoryInterface::class);
    $dispatcher = Mockery::mock(WorkflowDispatcherInterface::class);

    $today = new \DateTimeImmutable('today 00:00:00');
    $repo->shouldReceive('countCompletedSince')
        ->with(Mockery::on(fn (\DateTimeImmutable $d) => $d->format('Y-m-d H:i:s') === $today->format('Y-m-d H:i:s')))
        ->once()
        ->andReturn(5);

    $handler = new TranscribeVideoHandler($repo, $dispatcher);
    expect($handler->countCompletedToday())->toBe(5);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter="counts completed transcriptions since today midnight" --no-coverage`
Expected: FAIL — method not found

- [ ] **Step 3: Add `countCompletedToday()` to handler**

```php
public function countCompletedToday(): int
{
    $today = new \DateTimeImmutable('today 00:00:00');

    return $this->repository->countCompletedSince($today);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter="counts completed transcriptions since today midnight" --no-coverage`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Application/UseCases/TranscribeVideoHandler.php tests/Unit/Application/UseCases/TranscribeVideoHandlerTest.php
git commit -m "feat: add countCompletedToday() to handler"
```

---

### Task 2: Update controller — daily limit + max duration

**Files:**
- Modify: `app/Infrastructure/Adapters/Input/Web/TranscribeVideoController.php`
- Test: `tests/Feature/Feature/TranscribeVideoControllerTest.php` (or relevant feature test)

- [ ] **Step 1: Inject `SubtitleProviderInterface` into controller**

```php
use App\Application\Ports\Output\SubtitleProviderInterface;

public function __construct(
    private readonly TranscribeVideoHandler $handler,
    private readonly SubtitleProviderInterface $subtitleProvider,
) {
}
```

- [ ] **Step 2: Replace monthly quota check with daily + add max duration check in `create()`**

Replace the monthly quota block (lines 53-71) and add duration check after URL validation:

```php
// Check free tier daily quota (10 completed transcriptions/day).
$completedToday = $this->handler->countCompletedToday();
if ($completedToday >= 10) {
    $now = new \DateTimeImmutable();
    $tomorrow = $now->modify('tomorrow 00:00:00');
    $retryAfter = $tomorrow->getTimestamp() - $now->getTimestamp();

    return new JsonResponse([
        'error' => [
            'code' => 'RATE_LIMIT_EXCEEDED',
            'message' => 'Daily limit of 10 transcriptions reached. Come back tomorrow!',
            'details' => ['limit' => 10, 'used' => $completedToday],
        ],
    ], Response::HTTP_TOO_MANY_REQUESTS, [
        'Retry-After' => (string) $retryAfter,
    ]);
}

// Check video duration — reject videos longer than 30 minutes (1800 seconds)
// to protect against excessive API costs during free MVP phase.
$durationSec = $this->subtitleProvider->extractDuration($youtubeUrl);
if ($durationSec !== null && $durationSec > 1800) {
    return new JsonResponse([
        'error' => [
            'code' => 'VIDEO_TOO_LONG',
            'message' => 'Sorry, we currently only support videos up to 30 minutes long.',
            'details' => ['max_duration_sec' => 1800, 'video_duration_sec' => $durationSec],
        ],
    ], Response::HTTP_UNPROCESSABLE_ENTITY);
}
```

- [ ] **Step 3: Run existing tests to verify nothing broke**

Run: `vendor/bin/pest --filter="TranscribeVideoController" --no-coverage`
Expected: PASS (or skip if feature tests need DB)

- [ ] **Step 4: Commit**

```bash
git add app/Infrastructure/Adapters/Input/Web/TranscribeVideoController.php
git commit -m "feat: replace monthly quota with daily limit, add 30min max duration"
```

---

### Task 3: Update frontend error display

**Files:**
- Modify: `resources/js/composables/useTranscription.js` (or wherever API errors are displayed)

- [ ] **Step 1: Check current error handling in frontend**

Look at how `RATE_LIMIT_EXCEEDED` is currently displayed. The new error code `VIDEO_TOO_LONG` should also be handled.

- [ ] **Step 2: Update error handling if needed**

If the frontend already shows `error.message` generically, no changes needed. If it has specific handling for `RATE_LIMIT_EXCEEDED`, ensure `VIDEO_TOO_LONG` is also covered.

- [ ] **Step 3: Commit**

```bash
git add resources/js/composables/useTranscription.js
git commit -m "feat: handle VIDEO_TOO_LONG error in frontend"
```

---

### Task 4: Update PRD

**Files:**
- Modify: `Prd.md`

- [ ] **Step 1: Update quota section from monthly to daily**

Find the free tier limit section and update: "10 completed transcriptions/day" instead of "10 completed transcriptions/month".

- [ ] **Step 2: Add max duration rule**

Add: "Videos longer than 30 minutes are rejected with 422 VIDEO_TOO_LONG."

- [ ] **Step 3: Commit**

```bash
git add Prd.md
git commit -m "docs: update PRD with daily limit and max duration"
```
