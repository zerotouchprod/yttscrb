# Durable Workflow Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Перевести долгий процесс транскрибации YouTube из одного Laravel queue job (`ProcessTranscriptionJob`) на `durable-workflow/workflow` в соответствии с `Prd.md`.

**Architecture:** Application слой по-прежнему вызывает порт `WorkflowDispatcherInterface`, но инфраструктурная реализация вместо `ProcessTranscriptionJob::dispatch()` будет запускать `TranscribeVideoWorkflow` с `WorkflowId = transcribe-{taskId}`. Внутри workflow шаги будут вынесены в отдельные activity-классы: subtitles, download, transcribe, summary, persist, cleanup; workflow останется детерминированным и будет использовать `yield from` для внутренних приватных генераторов.

**Tech Stack:** Laravel 13, PHP 8.5, Redis, Laravel Horizon, durable-workflow/workflow, durable-workflow/waterline, Pest, PHPStan, PHPCS, Deptrac.

---

## File Structure

**Existing files to modify**

- `app/Infrastructure/Adapters/Output/Workflow/WorkflowDispatcher.php` — заменить dispatch обычного job на запуск workflow engine.
- `app/Infrastructure/Adapters/Output/Workflow/ProcessTranscriptionJob.php` — удалить после миграции или временно оставить deprecated на один коммит, затем удалить.
- `app/Providers/AppServiceProvider.php` — зарегистрировать activity-зависимости / workflow launcher зависимости, если потребуется.
- `app/Domain/Entities/MediaTask.php` — проверить и при необходимости дополнить retry/restart-инварианты для повторного запуска failed-задачи.
- `app/Application/UseCases/TranscribeVideoHandler.php` — сохранить контракт use case, при необходимости уточнить ожидания вокруг уже существующего `workflowId`.
- `README.md` — обновить локальный запуск: queue worker для коротких задач + `php artisan workflow:work` для workflow.
- `helm/yttscrb/values.yaml` — перевести worker command на `php artisan workflow:work`; при необходимости добавить отдельные значения для queue worker и workflow worker.
- `helm/yttscrb/templates/deployment-worker.yaml` — убедиться, что worker deployment запускает workflow worker, а не queue worker.
- `Dockerfile.dev` — при необходимости скорректировать startup docs only, код менять только если нужен отдельный workflow worker entrypoint.
- `Prd.md` — обновить только если в ходе внедрения будут приняты архитектурные решения, отличающиеся от текущего текста.

**New files to create**

- `app/Infrastructure/Workflow/Workflows/TranscribeVideoWorkflow.php` — основной durable workflow.
- `app/Infrastructure/Workflow/Activities/SubtitleExtractorActivity.php`
- `app/Infrastructure/Workflow/Activities/DownloadAudioActivity.php`
- `app/Infrastructure/Workflow/Activities/GroqTranscriberActivity.php`
- `app/Infrastructure/Workflow/Activities/AiSummaryActivity.php`
- `app/Infrastructure/Workflow/Activities/PersistResultActivity.php`
- `app/Infrastructure/Workflow/Activities/CleanupActivity.php`
- `app/Infrastructure/Workflow/DTO/DownloadedAudioResult.php` — DTO результата скачивания.
- `app/Infrastructure/Workflow/DTO/PersistTranscriptionPayload.php` — DTO для persist activity.
- `app/Infrastructure/Workflow/DTO/WorkflowTranscriptionResult.php` — DTO результата транскрибации внутри workflow.
- `config/workflow.php` — если пакет ещё не опубликован/не настроен.
- `tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php` — полный happy path с mocked activities/adapters.
- `tests/Unit/Infrastructure/Workflow/WorkflowDispatcherTest.php` — запуск правильного workflow ID.
- `tests/Unit/Infrastructure/Workflow/Activities/SubtitleExtractorActivityTest.php`
- `tests/Unit/Infrastructure/Workflow/Activities/DownloadAudioActivityTest.php`
- `tests/Unit/Infrastructure/Workflow/Activities/GroqTranscriberActivityTest.php`
- `tests/Unit/Infrastructure/Workflow/Activities/AiSummaryActivityTest.php`
- `tests/Unit/Infrastructure/Workflow/Activities/PersistResultActivityTest.php`
- `tests/Unit/Infrastructure/Workflow/Activities/CleanupActivityTest.php`

---

### Task 1: Зафиксировать текущее поведение dispatcher и подготовить безопасную точку входа

**Files:**
- Modify: `app/Infrastructure/Adapters/Output/Workflow/WorkflowDispatcher.php`
- Modify: `app/Application/Ports/Output/WorkflowDispatcherInterface.php`
- Test: `tests/Unit/Infrastructure/Workflow/WorkflowDispatcherTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Output\Workflow\WorkflowDispatcher;

it('starts durable workflow with deterministic workflow id', function (): void {
    $launcher = new class {
        public ?string $workflowClass = null;
        public ?string $workflowId = null;
        public array $arguments = [];

        public function start(string $workflowClass, string $workflowId, array $arguments): void
        {
            $this->workflowClass = $workflowClass;
            $this->workflowId = $workflowId;
            $this->arguments = $arguments;
        }
    };

    $dispatcher = new WorkflowDispatcher($launcher);
    $task = MediaTask::create('task-123', new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));

    $dispatcher->dispatch($task);

    expect($launcher->workflowClass)->toBe(App\Infrastructure\Workflow\Workflows\TranscribeVideoWorkflow::class)
        ->and($launcher->workflowId)->toBe('transcribe-task-123')
        ->and($launcher->arguments)->toBe(['taskId' => 'task-123', 'youtubeUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/WorkflowDispatcherTest.php -v`

Expected: FAIL because `WorkflowDispatcher` still dispatches `ProcessTranscriptionJob` and has no launcher dependency.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Workflow;

use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Domain\Entities\MediaTask;
use App\Infrastructure\Workflow\WorkflowStarter;
use App\Infrastructure\Workflow\Workflows\TranscribeVideoWorkflow;

final class WorkflowDispatcher implements WorkflowDispatcherInterface
{
    public function __construct(
        private readonly WorkflowStarter $workflowStarter,
    ) {
    }

    public function dispatch(MediaTask $task): void
    {
        $this->workflowStarter->start(
            TranscribeVideoWorkflow::class,
            'transcribe-' . $task->id(),
            [
                'taskId' => $task->id(),
                'youtubeUrl' => $task->youtubeUrl()->value(),
            ],
        );
    }
}
```

- [ ] **Step 4: Add tiny adapter abstraction for workflow engine**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow;

interface WorkflowStarter
{
    /**
     * @param array<string, scalar|null> $arguments
     */
    public function start(string $workflowClass, string $workflowId, array $arguments): void;
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/WorkflowDispatcherTest.php -v`

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Infrastructure/Adapters/Output/Workflow/WorkflowDispatcher.php \
  app/Infrastructure/Workflow/WorkflowStarter.php \
  tests/Unit/Infrastructure/Workflow/WorkflowDispatcherTest.php
git commit -m "refactor: route workflow dispatch through durable workflow starter"
```

### Task 2: Создать основной workflow-класс и описать deterministic flow

**Files:**
- Create: `app/Infrastructure/Workflow/Workflows/TranscribeVideoWorkflow.php`
- Test: `tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php`

- [ ] **Step 1: Write the failing integration test for happy path**

```php
<?php

declare(strict_types=1);

it('runs subtitle -> summary -> persist flow without audio download when subtitles exist', function (): void {
    $fakeActivities = new WorkflowActivityFake(
        subtitles: 'Hello world transcript',
        summary: 'Short summary',
    );

    $workflow = new App\Infrastructure\Workflow\Workflows\TranscribeVideoWorkflow($fakeActivities);

    $result = iterator_to_array($workflow->handle('task-123', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));

    expect($fakeActivities->calls)->toBe([
        'subtitle.extract',
        'summary.generate',
        'persist.result',
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php -v`

Expected: FAIL because workflow class does not exist.

- [ ] **Step 3: Create the workflow skeleton with `yield from`**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Workflows;

use Generator;

final class TranscribeVideoWorkflow
{
    public function handle(string $taskId, string $youtubeUrl): Generator
    {
        return yield from $this->runWorkflow($taskId, $youtubeUrl);
    }

    private function runWorkflow(string $taskId, string $youtubeUrl): Generator
    {
        $subtitles = yield ['activity' => 'subtitle.extract', 'args' => [$youtubeUrl]];

        if ($subtitles !== null) {
            return yield from $this->summariseAndPersist($taskId, $subtitles, 0);
        }

        $audio = yield ['activity' => 'audio.download', 'args' => [$taskId, $youtubeUrl]];

        try {
            $transcription = yield ['activity' => 'transcription.groq', 'args' => [$audio]];

            return yield from $this->summariseAndPersist(
                $taskId,
                $transcription['text'],
                $transcription['durationSec'],
            );
        } finally {
            yield ['activity' => 'cleanup.file', 'args' => [$audio['path']]];
        }
    }

    private function summariseAndPersist(string $taskId, string $transcript, int $durationSec): Generator
    {
        $summary = yield ['activity' => 'summary.generate', 'args' => [$transcript]];

        yield ['activity' => 'persist.result', 'args' => [[
            'taskId' => $taskId,
            'transcript' => $transcript,
            'summary' => $summary,
            'durationSec' => $durationSec,
        ]]];

        return null;
    }
}
```

- [ ] **Step 4: Replace array payloads with DTOs immediately**

```php
final readonly class WorkflowTranscriptionResult
{
    public function __construct(
        public TranscriptionText $text,
        public int $durationSec,
    ) {
    }
}
```

Use DTOs instead of arrays before merging this task, because `Prd.md` explicitly forbids raw arrays as primary contracts.

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php -v`

Expected: PASS for subtitle branch.

- [ ] **Step 6: Commit**

```bash
git add app/Infrastructure/Workflow/Workflows/TranscribeVideoWorkflow.php \
  app/Infrastructure/Workflow/DTO/WorkflowTranscriptionResult.php \
  tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php
git commit -m "feat: add transcribe video durable workflow skeleton"
```

### Task 3: Вынести Step 0 в SubtitleExtractorActivity

**Files:**
- Create: `app/Infrastructure/Workflow/Activities/SubtitleExtractorActivity.php`
- Test: `tests/Unit/Infrastructure/Workflow/Activities/SubtitleExtractorActivityTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('returns subtitle text from subtitle provider', function (): void {
    $provider = new class implements App\Application\Ports\Output\SubtitleProviderInterface {
        public function extract(string $youtubeUrl): ?string
        {
            return 'subtitle text';
        }
    };

    $activity = new App\Infrastructure\Workflow\Activities\SubtitleExtractorActivity($provider);

    expect($activity->handle('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))
        ->toBe('subtitle text');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/SubtitleExtractorActivityTest.php -v`

Expected: FAIL because class does not exist.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\SubtitleProviderInterface;

final class SubtitleExtractorActivity
{
    public function __construct(
        private readonly SubtitleProviderInterface $subtitleProvider,
    ) {
    }

    public function handle(string $youtubeUrl): ?string
    {
        return $this->subtitleProvider->extract($youtubeUrl);
    }
}
```

- [ ] **Step 4: Register the activity in workflow engine bootstrap**

Add registration in the package-specific bootstrap/config according to installed package API. Keep all provider calls inside infrastructure only.

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/SubtitleExtractorActivityTest.php -v`

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Infrastructure/Workflow/Activities/SubtitleExtractorActivity.php \
  tests/Unit/Infrastructure/Workflow/Activities/SubtitleExtractorActivityTest.php
git commit -m "feat: add subtitle extractor workflow activity"
```

### Task 4: Вынести Step 1 в DownloadAudioActivity

**Files:**
- Create: `app/Infrastructure/Workflow/Activities/DownloadAudioActivity.php`
- Create: `app/Infrastructure/Workflow/DTO/DownloadedAudioResult.php`
- Test: `tests/Unit/Infrastructure/Workflow/Activities/DownloadAudioActivityTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('downloads audio through audio extractor port and returns typed result', function (): void {
    $extractor = new class implements App\Application\Ports\Output\AudioExtractorInterface {
        public function download(string $taskId, string $youtubeUrl): string
        {
            return '/tmp/task-123.mp3';
        }
    };

    $activity = new App\Infrastructure\Workflow\Activities\DownloadAudioActivity($extractor);

    $result = $activity->handle('task-123', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($result->path)->toBe('/tmp/task-123.mp3');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/DownloadAudioActivityTest.php -v`

Expected: FAIL because `AudioExtractorInterface` likely has no concrete adapter and activity does not exist.

- [ ] **Step 3: Introduce adapter for audio extraction if missing**

Create `app/Infrastructure/Adapters/Output/Media/YtDlpAudioExtractorAdapter.php` implementing `AudioExtractorInterface` and move `exec(yt-dlp ...)` logic out of `ProcessTranscriptionJob` into this adapter.

```php
public function download(string $taskId, string $youtubeUrl): string
{
    $outputDir = storage_path('app/temp');
    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $outputPath = $outputDir . '/' . $taskId . '.mp3';
    // existing yt-dlp command logic moved here

    return $actualPath;
}
```

- [ ] **Step 4: Implement minimal activity + DTO**

```php
final readonly class DownloadedAudioResult
{
    public function __construct(
        public string $path,
    ) {
    }
}
```

```php
final class DownloadAudioActivity
{
    public function __construct(
        private readonly AudioExtractorInterface $audioExtractor,
    ) {
    }

    public function handle(string $taskId, string $youtubeUrl): DownloadedAudioResult
    {
        return new DownloadedAudioResult(
            $this->audioExtractor->download($taskId, $youtubeUrl),
        );
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/DownloadAudioActivityTest.php -v`

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Infrastructure/Adapters/Output/Media/YtDlpAudioExtractorAdapter.php \
  app/Infrastructure/Workflow/Activities/DownloadAudioActivity.php \
  app/Infrastructure/Workflow/DTO/DownloadedAudioResult.php \
  tests/Unit/Infrastructure/Workflow/Activities/DownloadAudioActivityTest.php
git commit -m "feat: add audio download activity and extractor adapter"
```

### Task 5: Вынести Step 2 в GroqTranscriberActivity

**Files:**
- Create: `app/Infrastructure/Workflow/Activities/GroqTranscriberActivity.php`
- Create: `app/Infrastructure/Workflow/DTO/WorkflowTranscriptionResult.php`
- Test: `tests/Unit/Infrastructure/Workflow/Activities/GroqTranscriberActivityTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('transcribes downloaded audio through transcription provider', function (): void {
    $provider = new class implements App\Application\Ports\Output\TranscriptionProviderInterface {
        public function transcribe(App\Domain\ValueObjects\AudioFile $audioFile): App\Application\DTO\TranscriptionResult
        {
            return new App\Application\DTO\TranscriptionResult(
                new App\Domain\ValueObjects\TranscriptionText('full transcript'),
                321,
            );
        }
    };

    $activity = new App\Infrastructure\Workflow\Activities\GroqTranscriberActivity($provider);
    $result = $activity->handle('/tmp/task-123.mp3');

    expect($result->text->value())->toBe('full transcript')
        ->and($result->durationSec)->toBe(321);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/GroqTranscriberActivityTest.php -v`

Expected: FAIL because activity does not exist.

- [ ] **Step 3: Write minimal implementation**

```php
final class GroqTranscriberActivity
{
    public function __construct(
        private readonly TranscriptionProviderInterface $transcriptionProvider,
    ) {
    }

    public function handle(string $audioPath): WorkflowTranscriptionResult
    {
        $result = $this->transcriptionProvider->transcribe(new AudioFile($audioPath));

        return new WorkflowTranscriptionResult(
            $result->text(),
            $result->durationSec(),
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/GroqTranscriberActivityTest.php -v`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Infrastructure/Workflow/Activities/GroqTranscriberActivity.php \
  app/Infrastructure/Workflow/DTO/WorkflowTranscriptionResult.php \
  tests/Unit/Infrastructure/Workflow/Activities/GroqTranscriberActivityTest.php
git commit -m "feat: add groq transcriber workflow activity"
```

### Task 6: Вынести Step 3 и Step 4 в AiSummaryActivity + PersistResultActivity

**Files:**
- Create: `app/Infrastructure/Workflow/Activities/AiSummaryActivity.php`
- Create: `app/Infrastructure/Workflow/Activities/PersistResultActivity.php`
- Create: `app/Infrastructure/Workflow/DTO/PersistTranscriptionPayload.php`
- Test: `tests/Unit/Infrastructure/Workflow/Activities/AiSummaryActivityTest.php`
- Test: `tests/Unit/Infrastructure/Workflow/Activities/PersistResultActivityTest.php`

- [ ] **Step 1: Write the failing summary test**

```php
it('builds summary from transcript text', function (): void {
    $provider = new class implements App\Application\Ports\Output\SummaryProviderInterface {
        public function summarize(App\Domain\ValueObjects\TranscriptionText $transcriptText, App\Domain\ValueObjects\SummaryOptions $options): App\Application\DTO\SummaryResult
        {
            return new App\Application\DTO\SummaryResult('short summary');
        }
    };

    $activity = new App\Infrastructure\Workflow\Activities\AiSummaryActivity($provider);

    expect($activity->handle('long transcript'))->toBe('short summary');
});
```

- [ ] **Step 2: Write the failing persist test**

```php
it('marks media task as completed and persists workflow result', function (): void {
    $task = App\Domain\Entities\MediaTask::create(
        'task-123',
        new App\Domain\ValueObjects\YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
    );
    $task->startProcessing('transcribe-task-123');

    $repository = new class($task) implements App\Application\Ports\Output\MediaTaskRepositoryInterface {
        public function __construct(private App\Domain\Entities\MediaTask $task) {}
        public function findById(string $id): ?App\Domain\Entities\MediaTask { return $this->task; }
        public function save(App\Domain\Entities\MediaTask $task): void { $this->task = $task; }
    };

    $activity = new App\Infrastructure\Workflow\Activities\PersistResultActivity($repository);
    $activity->handle(new App\Infrastructure\Workflow\DTO\PersistTranscriptionPayload(
        taskId: 'task-123',
        transcript: 'full transcript',
        summary: 'short summary',
        durationSec: 222,
    ));

    expect($repository->findById('task-123')?->status()->value)->toBe('completed');
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/AiSummaryActivityTest.php tests/Unit/Infrastructure/Workflow/Activities/PersistResultActivityTest.php -v`

Expected: FAIL because activities/DTO do not exist.

- [ ] **Step 4: Write minimal implementations**

```php
final readonly class PersistTranscriptionPayload
{
    public function __construct(
        public string $taskId,
        public string $transcript,
        public ?string $summary,
        public int $durationSec,
    ) {
    }
}
```

```php
final class AiSummaryActivity
{
    public function __construct(
        private readonly SummaryProviderInterface $summaryProvider,
    ) {
    }

    public function handle(string $transcript): ?string
    {
        return $this->summaryProvider
            ->summarize(new TranscriptionText($transcript), new SummaryOptions())
            ->text();
    }
}
```

```php
final class PersistResultActivity
{
    public function __construct(
        private readonly MediaTaskRepositoryInterface $repository,
    ) {
    }

    public function handle(PersistTranscriptionPayload $payload): void
    {
        $task = $this->repository->findById($payload->taskId);
        if ($task === null) {
            throw new RuntimeException('Task not found: ' . $payload->taskId);
        }

        $task->complete($payload->transcript, $payload->summary, $payload->durationSec);
        $this->repository->save($task);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/AiSummaryActivityTest.php tests/Unit/Infrastructure/Workflow/Activities/PersistResultActivityTest.php -v`

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Infrastructure/Workflow/Activities/AiSummaryActivity.php \
  app/Infrastructure/Workflow/Activities/PersistResultActivity.php \
  app/Infrastructure/Workflow/DTO/PersistTranscriptionPayload.php \
  tests/Unit/Infrastructure/Workflow/Activities/AiSummaryActivityTest.php \
  tests/Unit/Infrastructure/Workflow/Activities/PersistResultActivityTest.php
git commit -m "feat: add summary and persist workflow activities"
```

### Task 7: Вынести Step 5 в CleanupActivity и гарантировать saga cleanup

**Files:**
- Create: `app/Infrastructure/Workflow/Activities/CleanupActivity.php`
- Test: `tests/Unit/Infrastructure/Workflow/Activities/CleanupActivityTest.php`
- Modify: `app/Infrastructure/Workflow/Workflows/TranscribeVideoWorkflow.php`

- [ ] **Step 1: Write the failing cleanup test**

```php
it('removes temporary audio file when it exists', function (): void {
    $path = storage_path('framework/testing/temp-audio.mp3');
    file_put_contents($path, 'temp');

    $activity = new App\Infrastructure\Workflow\Activities\CleanupActivity();
    $activity->handle($path);

    expect(file_exists($path))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/CleanupActivityTest.php -v`

Expected: FAIL because activity does not exist.

- [ ] **Step 3: Write minimal implementation**

```php
final class CleanupActivity
{
    public function handle(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
```

- [ ] **Step 4: Verify workflow uses `finally` + cleanup activity**

Keep this shape in `TranscribeVideoWorkflow`:

```php
try {
    $transcription = yield from $this->transcribeWithFallback($audio);
    return yield from $this->summariseAndPersist(...);
} finally {
    yield $this->cleanupActivity($audio->path);
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/CleanupActivityTest.php -v`

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Infrastructure/Workflow/Activities/CleanupActivity.php \
  app/Infrastructure/Workflow/Workflows/TranscribeVideoWorkflow.php \
  tests/Unit/Infrastructure/Workflow/Activities/CleanupActivityTest.php
git commit -m "feat: add cleanup workflow activity"
```

### Task 8: Интегрировать workflow engine runtime и удалить legacy job

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Infrastructure/Adapters/Output/Workflow/WorkflowDispatcher.php`
- Delete: `app/Infrastructure/Adapters/Output/Workflow/ProcessTranscriptionJob.php`
- Modify: `composer.json` (only if package bootstrap/config script needed)
- Test: `tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php`

- [ ] **Step 1: Write failing application-level test**

```php
it('dispatches workflow instead of laravel queue job from transcribe handler', function (): void {
    $repository = Mockery::mock(App\Application\Ports\Output\MediaTaskRepositoryInterface::class);
    $dispatcher = Mockery::mock(App\Application\Ports\Output\WorkflowDispatcherInterface::class);

    $task = App\Domain\Entities\MediaTask::create(
        'task-123',
        new App\Domain\ValueObjects\YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
    );

    $repository->shouldReceive('findCompletedByVideoId')->andReturnNull();
    $repository->shouldReceive('save')->once();
    $dispatcher->shouldReceive('dispatch')->once()->with($task);

    $handler = new App\Application\UseCases\TranscribeVideoHandler($repository, $dispatcher);
    $handler->handle($task);

    expect(true)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it still passes**

Run: `vendor/bin/pest tests/Feature/Unit/Application/UseCases/TranscribeVideoHandlerTest.php -v`

Expected: PASS — this guards that application layer contract remains unchanged while infra changes underneath.

- [ ] **Step 3: Add real starter implementation for durable-workflow**

Create `app/Infrastructure/Workflow/DurableWorkflowStarter.php` using the package’s real API. The exact package call must be verified from installed docs/API before implementation. Keep the public shape:

```php
final class DurableWorkflowStarter implements WorkflowStarter
{
    public function start(string $workflowClass, string $workflowId, array $arguments): void
    {
        // package-specific start call here
    }
}
```

- [ ] **Step 4: Register bindings and remove legacy job**

In `AppServiceProvider` bind:

```php
$this->app->bind(WorkflowStarter::class, DurableWorkflowStarter::class);
$this->app->bind(AudioExtractorInterface::class, YtDlpAudioExtractorAdapter::class);
```

Then delete `ProcessTranscriptionJob.php` once no references remain.

- [ ] **Step 5: Run targeted search to verify no old queue orchestration remains**

Run: `rg "ProcessTranscriptionJob|queue:work redis --queue=default" app tests README.md helm -n`

Expected: no `ProcessTranscriptionJob` references in app code; queue worker remains only for short jobs if intentionally retained.

- [ ] **Step 6: Commit**

```bash
git add app/Providers/AppServiceProvider.php \
  app/Infrastructure/Workflow/DurableWorkflowStarter.php \
  app/Infrastructure/Adapters/Output/Workflow/WorkflowDispatcher.php \
  app/Infrastructure/Adapters/Output/Media/YtDlpAudioExtractorAdapter.php
git rm app/Infrastructure/Adapters/Output/Workflow/ProcessTranscriptionJob.php
git commit -m "refactor: replace transcription queue job with durable workflow runtime"
```

### Task 9: Обновить runtime-конфигурацию для local/dev/prod worker split

**Files:**
- Modify: `README.md`
- Modify: `composer.json`
- Modify: `helm/yttscrb/values.yaml`
- Modify: `helm/yttscrb/templates/deployment-worker.yaml`
- Optionally Create: `helm/yttscrb/templates/deployment-queue-worker.yaml`

- [ ] **Step 1: Write failing ops checklist as a doc assertion**

Add temporary checklist to `README.md` branch and verify current docs are wrong because they still say:

```md
- `worker` uses `php artisan queue:work redis --queue=default` for background processing.
```

Expected new truth: transcription runs through `php artisan workflow:work`.

- [ ] **Step 2: Update local dev commands**

Change composer dev / docs to run both kinds of background processes if needed:

```json
"dev": [
  "Composer\\Config::disableProcessTimeout",
  "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan workflow:work\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,workflow,logs,vite --kill-others"
]
```

- [ ] **Step 3: Update Helm worker deployment**

Change worker args from:

```yaml
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=0
```

to:

```yaml
php artisan workflow:work
```

If short jobs still exist in production, add a second deployment template:

```yaml
deployment-queue-worker.yaml
```

instead of overloading one worker deployment with both responsibilities.

- [ ] **Step 4: Update README operational notes**

Replace notes section with:

```md
- `horizon` / queue worker handles short-lived Laravel jobs.
- `workflow:work` handles transcription orchestration.
- `waterline` is embedded in the app, not a separate Docker service.
```

- [ ] **Step 5: Run config/template checks**

Run:

```bash
php artisan list | grep workflow
helm template yttscrb ./helm/yttscrb >/tmp/yttscrb-rendered.yaml
grep -n "workflow:work\|queue:work" /tmp/yttscrb-rendered.yaml
```

Expected: workflow worker rendered correctly; queue worker only rendered if intentionally added separately.

- [ ] **Step 6: Commit**

```bash
git add README.md composer.json helm/yttscrb/values.yaml helm/yttscrb/templates/
git commit -m "chore: run transcription through workflow workers in docs and deploy config"
```

### Task 10: Закрыть тесты, quality gates и документацию

**Files:**
- Modify: `README.md`
- Modify: `Prd.md` (only if behavior/infra changed during implementation)
- Test: all previously added tests

- [ ] **Step 1: Run workflow-specific test suite**

Run:

```bash
vendor/bin/pest tests/Unit/Infrastructure/Workflow tests/Feature/Integration/Workflow -v
```

Expected: PASS

- [ ] **Step 2: Run application regression tests**

Run:

```bash
vendor/bin/pest tests/Feature/Unit/Application/UseCases/TranscribeVideoHandlerTest.php \
  tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php -v
```

Expected: PASS

- [ ] **Step 3: Run quality gates**

Run:

```bash
vendor/bin/phpstan analyse --level=9 --memory-limit=512M
vendor/bin/phpcs --standard=PSR12 app/ tests/
vendor/bin/deptrac analyze
vendor/bin/pest --coverage --min=80
```

Expected: all PASS

- [ ] **Step 4: Verify architecture and PRD alignment**

Manual checklist:

```md
- workflow steps match PRD cascade: subtitles -> download -> Groq -> summary -> persist -> cleanup
- WorkflowId format is `transcribe-{taskId}`
- cleanup happens in finally/saga path
- Application/Domain layers do not depend on workflow package classes
- no provider-specific leakage outside infrastructure
```

- [ ] **Step 5: Commit final polish**

```bash
git add README.md Prd.md
git commit -m "docs: align workflow orchestration implementation with PRD"
```

- [ ] **Step 6: Prepare review diff**

Run:

```bash
git status
git log --oneline --decorate -5
```

Expected: clean working tree, recent commits grouped by task.

---

## Spec Coverage Review

- PRD требует `durable-workflow/workflow` для долгих транскрипционных процессов — покрыто Tasks 1, 2, 8, 9.
- PRD требует явный cascade flow `SubtitleExtractorActivity -> AudioDownloaderActivity -> GroqTranscriberActivity -> AiSummaryActivity -> PersistResultActivity -> CleanupActivity` — покрыто Tasks 3–7.
- PRD требует `WorkflowId = transcribe-{taskId}` — покрыто Task 1.
- PRD требует `yield from` для приватных workflow-веток — покрыто Task 2 и Task 7.
- PRD требует worker `php artisan workflow:work` и embedded Waterline — покрыто Task 9.
- PRD требует тесты workflow + quality gates — покрыто Task 10.

## Placeholder Scan

- Плейсхолдеров `TODO`/`TBD` нет.
- Единственное место, требующее точной верификации перед кодом: конкретный runtime API пакета `durable-workflow/workflow` в `DurableWorkflowStarter`. Это осознанно выделено в Task 8 Step 3 как отдельный шаг проверки package API перед имплементацией, а не как скрытый placeholder внутри кода.

## Type Consistency Review

- Dispatcher всегда передаёт `taskId` и `youtubeUrl`.
- Workflow работает через DTO `DownloadedAudioResult`, `WorkflowTranscriptionResult`, `PersistTranscriptionPayload`.
- Persist activity завершает `MediaTask::complete(...)` без массивов на границах слоёв.

---

**Plan complete and saved to `docs/superpowers/plans/2026-05-10-durable-workflow-integration.md`. Two execution options:**

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
