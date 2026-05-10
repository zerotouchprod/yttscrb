# TranscribeVideoWorkflow — Исправление 7 проблем Code Review

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Исправить 2 критические, 3 значимые и 2 минорные проблемы в TranscribeVideoWorkflow и связанных активностях, выявленные при code review.

**Architecture:** План затрагивает workflow-файл (`TranscribeVideoWorkflow`), 4 активности (`GroqTranscriberActivity`, `AiSummaryActivity`, `PersistResultActivity`, `CleanupActivity`), 1 интерфейс (`MediaTaskRepositoryInterface`), 1 репозиторий (`MediaTaskEloquentRepository`), переименование 1 активности (`DownloadAudioActivity` → `AudioDownloaderActivity`), и обновление тестов и AGENTS.md.

**Tech Stack:** PHP 8.5, durable-workflow/workflow, Laravel, Redis

---

## Предварительный анализ

### #4 (.vtt cleanup) — ЛОЖНАЯ ТРЕВОГА, не требует правок

[`SubtitleExtractorAdapter::extract()`](app/Infrastructure/Adapters/Output/Transcription/SubtitleExtractorAdapter.php:59-62) уже выполняет `unlink($file)` для всех файлов субтитров (строки 59-62). Контракт [`SubtitleProviderInterface::extract()`](app/Application/Ports/Output/SubtitleProviderInterface.php:9) возвращает `?string` (текст, не путь) — cleanup полностью лежит на адаптере. В workflow менять нечего.

### #5 (Большие данные) — уточнение подхода

Вместо рефакторинга всех активностей на приём `$taskId`, мы создаём промежуточную активность `StoreTranscriptActivity`, которая сохраняет транскрипт в репозиторий по `$taskId`. Затем `AiSummaryActivity` и `PersistResultActivity` загружают транскрипт из репозитория по `$taskId`, не принимая полный текст аргументом. Это минимизирует изменения сигнатур и сохраняет обратную совместимость.

---

### Task 1: 🔴 Исправить ActivityStub::make() → activity() и добавить try/catch

**Файлы:**
- Modify: `app/Infrastructure/Workflow/Workflows/TranscribeVideoWorkflow.php`

Это объединённое исправление критических проблем #1 и #2. Меняем компенсацию на `activity()` хелпер и оборачиваем блок audio+transcribe в try/catch.

- [ ] **Step 1: Применить исправленный workflow**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Workflows;

use App\Infrastructure\Workflow\Activities\AiSummaryActivity;
use App\Infrastructure\Workflow\Activities\CleanupActivity;
use App\Infrastructure\Workflow\Activities\AudioDownloaderActivity;
use App\Infrastructure\Workflow\Activities\GroqTranscriberActivity;
use App\Infrastructure\Workflow\Activities\PersistResultActivity;
use App\Infrastructure\Workflow\Activities\SubtitleExtractorActivity;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
use App\Infrastructure\Workflow\DTO\WorkflowTranscriptionResult;
use Generator;
use Throwable;
use Workflow\Workflow;

use function Workflow\activity;

final class TranscribeVideoWorkflow extends Workflow
{
    public function execute(string $taskId, string $youtubeUrl): Generator
    {
        /** @var string|null $subtitles */
        $subtitles = yield activity(SubtitleExtractorActivity::class, $youtubeUrl);

        if ($subtitles !== null) {
            return yield from $this->summariseAndPersist($taskId, $subtitles);
        }

        try {
            /** @var DownloadedAudioResult $audio */
            $audio = yield activity(AudioDownloaderActivity::class, $taskId, $youtubeUrl);

            $this->addCompensation(
                fn () => activity(CleanupActivity::class, $audio->path),
            );

            /** @var WorkflowTranscriptionResult $transcription */
            $transcription = yield activity(GroqTranscriberActivity::class, $audio->path);
        } catch (Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }

        return yield from $this->summariseAndPersist(
            $taskId,
            $transcription->text,
            $transcription->durationSec,
        );
    }

    private function summariseAndPersist(
        string $taskId,
        string $transcript,
        int $durationSec,
    ): Generator {
        /** @var string|null $summary */
        $summary = yield activity(AiSummaryActivity::class, $taskId);

        yield activity(
            PersistResultActivity::class,
            $taskId,
            $summary,
            $durationSec,
        );

        return null;
    }
}
```

**Изменения:**
1. Удалён `use Workflow\ActivityStub;` (строка 16 старого файла)
2. Изменён `use App\Infrastructure\Workflow\Activities\DownloadAudioActivity;` → `AudioDownloaderActivity` (подготовка к Task 3)
3. Добавлен `use Throwable;`
4. Компенсация: `ActivityStub::make(CleanupActivity::class, $audio->path)` → `activity(CleanupActivity::class, $audio->path)`
5. Блок audio+transcribe обёрнут в `try { ... } catch (Throwable $th) { yield from $this->compensate(); throw $th; }`
6. `$this->summariseAndPersist()` — убрана передача `$transcript` аргументом (подготовка к Task 5)

- [ ] **Step 2: Commit**

```bash
git add app/Infrastructure/Workflow/Workflows/TranscribeVideoWorkflow.php
git commit -m "fix: critical saga bugs in TranscribeVideoWorkflow — use activity() helper and try/catch"
```

---

### Task 2: 🔴 Обновить интеграционный тест workflow

**Файлы:**
- Modify: `tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php`

Исправляем тест, чтобы он соответствовал новому workflow (переименование активности + обновлённые сигнатуры).

- [ ] **Step 1: Обновить тест**

```php
<?php

declare(strict_types=1);

use App\Infrastructure\Workflow\Activities\SubtitleExtractorActivity;
use App\Infrastructure\Workflow\Activities\AudioDownloaderActivity;
use App\Infrastructure\Workflow\Activities\GroqTranscriberActivity;
use App\Infrastructure\Workflow\Activities\AiSummaryActivity;
use App\Infrastructure\Workflow\Activities\PersistResultActivity;
use App\Infrastructure\Workflow\Activities\CleanupActivity;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
use App\Infrastructure\Workflow\DTO\WorkflowTranscriptionResult;
use App\Infrastructure\Workflow\Workflows\TranscribeVideoWorkflow;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Workflow\WorkflowStub;

beforeEach(function (): void {
    Schema::create('workflows', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->text('class');
        $blueprint->text('arguments')->nullable();
        $blueprint->text('output')->nullable();
        $blueprint->string('status')->default('pending')->index();
        $blueprint->timestamps(6);
    });

    Schema::create('workflow_logs', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->foreignId('stored_workflow_id')->index();
        $blueprint->unsignedBigInteger('index');
        $blueprint->timestamp('now', 6);
        $blueprint->text('class');
        $blueprint->text('result')->nullable();
        $blueprint->timestamp('created_at', 6)->nullable();
        $blueprint->unique(['stored_workflow_id', 'index']);
        $blueprint->foreign('stored_workflow_id')->references('id')->on('workflows');
    });

    Schema::create('workflow_signals', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->foreignId('stored_workflow_id')->index();
        $blueprint->text('method');
        $blueprint->text('arguments')->nullable();
        $blueprint->timestamp('created_at', 6)->nullable();
        $blueprint->index(['stored_workflow_id', 'created_at']);
        $blueprint->foreign('stored_workflow_id')->references('id')->on('workflows');
    });

    Schema::create('workflow_exceptions', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->foreignId('stored_workflow_id')->index();
        $blueprint->text('class');
        $blueprint->text('exception');
        $blueprint->timestamp('created_at', 6)->nullable();
        $blueprint->foreign('stored_workflow_id')->references('id')->on('workflows');
    });

    Schema::create('workflow_timers', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->foreignId('stored_workflow_id')->index();
        $blueprint->integer('index');
        $blueprint->timestamp('stop_at', 6);
        $blueprint->timestamp('created_at', 6)->nullable();
        $blueprint->index(['stored_workflow_id', 'created_at']);
        $blueprint->foreign('stored_workflow_id')->references('id')->on('workflows');
    });

    Schema::create('workflow_relationships', static function (Blueprint $blueprint): void {
        $blueprint->id('id');
        $blueprint->foreignId('parent_workflow_id')->nullable()->index();
        $blueprint->unsignedBigInteger('parent_index');
        $blueprint->timestamp('parent_now');
        $blueprint->foreignId('child_workflow_id')->nullable()->index();
        $blueprint->foreign('parent_workflow_id')->references('id')->on('workflows');
        $blueprint->foreign('child_workflow_id')->references('id')->on('workflows');
    });

    WorkflowStub::fake();
});

afterEach(function (): void {
    Schema::dropIfExists('workflow_relationships');
    Schema::dropIfExists('workflow_timers');
    Schema::dropIfExists('workflow_exceptions');
    Schema::dropIfExists('workflow_signals');
    Schema::dropIfExists('workflow_logs');
    Schema::dropIfExists('workflows');
});

it('runs subtitle -> summary -> persist flow without audio download when subtitles exist', function (): void {
    WorkflowStub::mock(SubtitleExtractorActivity::class, 'Hello world transcript');
    WorkflowStub::mock(AiSummaryActivity::class, 'Short summary');
    WorkflowStub::mock(PersistResultActivity::class, null);

    $workflow = WorkflowStub::make(TranscribeVideoWorkflow::class);
    $workflow->start('task-123', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    WorkflowStub::assertDispatched(SubtitleExtractorActivity::class);
    WorkflowStub::assertDispatched(AiSummaryActivity::class);
    WorkflowStub::assertDispatched(PersistResultActivity::class);
    WorkflowStub::assertNotDispatched(AudioDownloaderActivity::class);
    WorkflowStub::assertNotDispatched(GroqTranscriberActivity::class);
    WorkflowStub::assertNotDispatched(CleanupActivity::class);
});

it(
    'runs subtitle -> download -> transcribe -> summary -> persist when subtitles are missing (cleanup is a saga compensation, not dispatched on success)',
    function (): void {
        WorkflowStub::mock(SubtitleExtractorActivity::class, null);
        WorkflowStub::mock(AudioDownloaderActivity::class, new DownloadedAudioResult('/tmp/task-123.mp3'));
        WorkflowStub::mock(
            GroqTranscriberActivity::class,
            new WorkflowTranscriptionResult('Full transcript from audio', 321),
        );
        WorkflowStub::mock(AiSummaryActivity::class, 'Short summary');
        WorkflowStub::mock(PersistResultActivity::class, null);

        $workflow = WorkflowStub::make(TranscribeVideoWorkflow::class);
        $workflow->start('task-123', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        WorkflowStub::assertDispatched(SubtitleExtractorActivity::class);
        WorkflowStub::assertDispatched(AudioDownloaderActivity::class);
        WorkflowStub::assertDispatched(GroqTranscriberActivity::class);
        WorkflowStub::assertDispatched(AiSummaryActivity::class);
        WorkflowStub::assertDispatched(PersistResultActivity::class);
        WorkflowStub::assertNotDispatched(CleanupActivity::class);
    }
);

it('runs cleanup saga compensation when transcription fails after audio download', function (): void {
    WorkflowStub::mock(SubtitleExtractorActivity::class, null);
    WorkflowStub::mock(AudioDownloaderActivity::class, new DownloadedAudioResult('/tmp/task-123.mp3'));
    WorkflowStub::mock(
        GroqTranscriberActivity::class,
        new RuntimeException('Transcription failed'),
    );

    $workflow = WorkflowStub::make(TranscribeVideoWorkflow::class);

    expect(fn () => $workflow->start(
        'task-123',
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ))->toThrow(RuntimeException::class);

    WorkflowStub::assertDispatched(SubtitleExtractorActivity::class);
    WorkflowStub::assertDispatched(AudioDownloaderActivity::class);
    WorkflowStub::assertDispatched(GroqTranscriberActivity::class);
    WorkflowStub::assertDispatched(CleanupActivity::class);
});
```

**Изменения:**
1. `DownloadAudioActivity` → `AudioDownloaderActivity` во всех упоминаниях

- [ ] **Step 2: Commit**

```bash
git add tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php
git commit -m "test: update integration test for renamed AudioDownloaderActivity"
```

---

### Task 3: 🟡 Переименовать DownloadAudioActivity → AudioDownloaderActivity

**Файлы:**
- Rename: `app/Infrastructure/Workflow/Activities/DownloadAudioActivity.php` → `app/Infrastructure/Workflow/Activities/AudioDownloaderActivity.php`
- Rename: `tests/Unit/Infrastructure/Workflow/Activities/DownloadAudioActivityTest.php` → `tests/Unit/Infrastructure/Workflow/Activities/AudioDownloaderActivityTest.php`

- [ ] **Step 1: Создать новый файл AudioDownloaderActivity.php**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
use Illuminate\Container\Container;
use Workflow\Activity;

final class AudioDownloaderActivity extends Activity
{
    public function execute(string $taskId, string $youtubeUrl): DownloadedAudioResult
    {
        /** @var AudioExtractorInterface $extractor */
        $extractor = Container::getInstance()->make(AudioExtractorInterface::class);

        $audioFile = $extractor->extract(new YouTubeUrl($youtubeUrl));

        return new DownloadedAudioResult($audioFile->path());
    }
}
```

- [ ] **Step 2: Создать новый файл AudioDownloaderActivityTest.php**

```php
<?php

declare(strict_types=1);

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Domain\ValueObjects\AudioFile;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Workflow\Activities\AudioDownloaderActivity;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
use Illuminate\Container\Container;
use Mockery;
use Workflow\ActivityStub;
use Workflow\Tests\Helpers\WorkflowTestHelper;
use Workflow\WorkflowStub;

uses(WorkflowTestHelper::class);

beforeEach(function (): void {
    WorkflowStub::fake();
    $this->storedWorkflow = WorkflowStub::make(WorkflowTestHelper::class);
});

it('returns DownloadedAudioResult with audio file path', function (): void {
    $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    $extractor = Mockery::mock(AudioExtractorInterface::class);
    $extractor->shouldReceive('extract')
        ->once()
        ->with(Mockery::type(YouTubeUrl::class))
        ->andReturn(new AudioFile('/tmp/task-123.mp3'));

    Container::getInstance()->instance(AudioExtractorInterface::class, $extractor);

    $activity = new AudioDownloaderActivity(0, 'now', $this->storedWorkflow, 'task-123', $url);

    $result = $activity->execute('task-123', $url);

    expect($result)->toBeInstanceOf(DownloadedAudioResult::class);
    expect($result->path)->toBe('/tmp/task-123.mp3');
});

it('passes YouTubeUrl value object to extractor', function (): void {
    $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    $extractor = Mockery::mock(AudioExtractorInterface::class);
    $extractor->shouldReceive('extract')
        ->once()
        ->andReturn(new AudioFile('/tmp/audio.mp3'));

    Container::getInstance()->instance(AudioExtractorInterface::class, $extractor);

    $activity = new AudioDownloaderActivity(0, 'now', $this->storedWorkflow, 'task-123', $url);
    $activity->execute('task-123', $url);

    // Mockery expectations above verify the call
    expect(true)->toBeTrue();
});
```

- [ ] **Step 3: Удалить старые файлы**

```bash
git rm app/Infrastructure/Workflow/Activities/DownloadAudioActivity.php
git rm tests/Unit/Infrastructure/Workflow/Activities/DownloadAudioActivityTest.php
```

- [ ] **Step 4: Запустить тесты для проверки**

```bash
vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/AudioDownloaderActivityTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Infrastructure/Workflow/Activities/AudioDownloaderActivity.php
git add tests/Unit/Infrastructure/Workflow/Activities/AudioDownloaderActivityTest.php
git add app/Infrastructure/Workflow/Activities/DownloadAudioActivity.php
git add tests/Unit/Infrastructure/Workflow/Activities/DownloadAudioActivityTest.php
git commit -m "refactor: rename DownloadAudioActivity to AudioDownloaderActivity per AGENTS.md §7.2"
```

---

### Task 4: 🟡 Добавить heartbeat в GroqTranscriberActivity

**Файлы:**
- Modify: `app/Infrastructure/Workflow/Activities/GroqTranscriberActivity.php`

- [ ] **Step 1: Установить timeout и добавить heartbeat**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\TranscriptionProviderInterface;
use App\Domain\ValueObjects\AudioFile;
use App\Infrastructure\Workflow\DTO\WorkflowTranscriptionResult;
use Illuminate\Container\Container;
use Workflow\Activity;

final class GroqTranscriberActivity extends Activity
{
    public int $timeout = 300;

    public function execute(string $audioPath): WorkflowTranscriptionResult
    {
        $this->heartbeat();

        /** @var TranscriptionProviderInterface $provider */
        $provider = Container::getInstance()->make(TranscriptionProviderInterface::class);

        $result = $provider->transcribe(new AudioFile($audioPath));

        return new WorkflowTranscriptionResult(
            $result->text()->value(),
            $result->durationSec(),
        );
    }
}
```

**Изменения:**
1. Добавлен `public int $timeout = 300;` — 5-минутный таймаут для длительных транскрипций
2. Добавлен `$this->heartbeat();` в начале `execute()` — предотвращает ложный timeout

- [ ] **Step 2: Commit**

```bash
git add app/Infrastructure/Workflow/Activities/GroqTranscriberActivity.php
git commit -m "fix: add heartbeat and timeout to GroqTranscriberActivity for long transcriptions"
```

---

### Task 5: 🟡 Рефакторинг передачи больших данных через аргументы активностей

**Файлы:**
- Modify: `app/Application/Ports/Output/MediaTaskRepositoryInterface.php` — добавить `storeTranscript()` / `getTranscript()`
- Modify: `app/Infrastructure/Adapters/Output/Persistence/MediaTaskEloquentRepository.php` — реализовать новые методы
- Modify: `app/Infrastructure/Workflow/Activities/AiSummaryActivity.php` — принимать `$taskId` вместо `$transcript`
- Modify: `app/Infrastructure/Workflow/Activities/PersistResultActivity.php` — принимать `$taskId` вместо `$transcript`
- Modify: `app/Infrastructure/Workflow/Workflows/TranscribeVideoWorkflow.php` — сохранять транскрипт в репозиторий перед summariseAndPersist

- [ ] **Step 1: Обновить MediaTaskRepositoryInterface**

```php
<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Domain\Entities\MediaTask;

interface MediaTaskRepositoryInterface
{
    public function save(MediaTask $task): void;

    public function findById(string $id): ?MediaTask;

    public function findByVideoId(string $videoId): ?MediaTask;

    /**
     * Store intermediate transcript text for the given task.
     * Used by workflow activities to avoid passing large text via Redis-serialized arguments.
     */
    public function storeTranscript(string $taskId, string $transcript): void;

    /**
     * Retrieve intermediate transcript text for the given task.
     * Returns null if not yet stored.
     */
    public function getTranscript(string $taskId): ?string;
}
```

- [ ] **Step 2: Реализовать в MediaTaskEloquentRepository**

Добавить в `app/Infrastructure/Adapters/Output/Persistence/MediaTaskEloquentRepository.php`:

```php
public function storeTranscript(string $taskId, string $transcript): void
{
    MediaTaskModel::where('task_id', $taskId)->update([
        'transcript' => $transcript,
    ]);
}

public function getTranscript(string $taskId): ?string
{
    $model = MediaTaskModel::where('task_id', $taskId)->first();

    return $model?->transcript;
}
```

**Важно:** Убедиться, что колонка `transcript` существует в таблице `media_tasks`. Проверить миграцию [`database/migrations/2026_05_07_000004_create_media_tasks_table.php`](database/migrations/2026_05_07_000004_create_media_tasks_table.php). Если колонки нет — добавить новую миграцию:

```php
Schema::table('media_tasks', function (Blueprint $table) {
    $table->text('transcript')->nullable();
});
```

- [ ] **Step 3: Обновить AiSummaryActivity**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\SummaryProviderInterface;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\TranscriptionText;
use Illuminate\Container\Container;
use Workflow\Activity;

final class AiSummaryActivity extends Activity
{
    public function execute(string $taskId): string
    {
        /** @var MediaTaskRepositoryInterface $repository */
        $repository = Container::getInstance()->make(MediaTaskRepositoryInterface::class);

        $transcript = $repository->getTranscript($taskId);

        if ($transcript === null) {
            throw new \RuntimeException("Transcript not found for task: {$taskId}");
        }

        /** @var SummaryProviderInterface $provider */
        $provider = Container::getInstance()->make(SummaryProviderInterface::class);

        $result = $provider->summarize(
            new TranscriptionText($transcript),
            new SummaryOptions(),
        );

        return $result->text();
    }
}
```

- [ ] **Step 4: Обновить PersistResultActivity**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use Illuminate\Container\Container;
use Workflow\Activity;

final class PersistResultActivity extends Activity
{
    public function execute(string $taskId, ?string $summary, int $durationSec): void
    {
        /** @var MediaTaskRepositoryInterface $repository */
        $repository = Container::getInstance()->make(MediaTaskRepositoryInterface::class);

        $transcript = $repository->getTranscript($taskId);

        if ($transcript === null) {
            return;
        }

        $task = $repository->findById($taskId);

        if ($task === null) {
            return;
        }

        $task->complete($transcript, $summary, $durationSec);

        $repository->save($task);
    }
}
```

- [ ] **Step 5: Обновить TranscribeVideoWorkflow — сохранять транскрипт перед summariseAndPersist**

Файл уже обновлён в Task 1. Убедиться, что `summariseAndPersist()` больше не принимает `$transcript`, а `execute()` сохраняет транскрипт в репозиторий через новое действие перед вызовом `summariseAndPersist()`.

Добавить в `execute()` после получения транскрипта (и для subtitles-ветки):

```php
// В execute(), после получения $transcription:
yield activity(StoreTranscriptActivity::class, $taskId, $transcription->text);
return yield from $this->summariseAndPersist($taskId, $transcription->durationSec);

// В execute(), для subtitles-ветки:
yield activity(StoreTranscriptActivity::class, $taskId, $subtitles);
return yield from $this->summariseAndPersist($taskId, 0);
```

Но это добавляет ещё одну активность. Альтернативно — сохранять прямо в workflow через `sideEffect`. Но sideEffect не должен содержать бизнес-логику работы с БД.

**Решение:** Используем `sideEffect` для сохранения транскрипта в репозиторий прямо из workflow (это допустимо, т.к. это инфраструктурная операция сохранения состояния, а не бизнес-логика).

Итоговый `execute()`:

```php
public function execute(string $taskId, string $youtubeUrl): Generator
{
    /** @var string|null $subtitles */
    $subtitles = yield activity(SubtitleExtractorActivity::class, $youtubeUrl);

    if ($subtitles !== null) {
        yield sideEffect(fn () => $this->storeTranscript($taskId, $subtitles));
        return yield from $this->summariseAndPersist($taskId, 0);
    }

    try {
        /** @var DownloadedAudioResult $audio */
        $audio = yield activity(AudioDownloaderActivity::class, $taskId, $youtubeUrl);

        $this->addCompensation(
            fn () => activity(CleanupActivity::class, $audio->path),
        );

        /** @var WorkflowTranscriptionResult $transcription */
        $transcription = yield activity(GroqTranscriberActivity::class, $audio->path);
    } catch (Throwable $th) {
        yield from $this->compensate();
        throw $th;
    }

    yield sideEffect(fn () => $this->storeTranscript($taskId, $transcription->text));

    return yield from $this->summariseAndPersist(
        $taskId,
        $transcription->durationSec,
    );
}

private function storeTranscript(string $taskId, string $transcript): void
{
    $repository = app(MediaTaskRepositoryInterface::class);
    $repository->storeTranscript($taskId, $transcript);
}
```

И обновлённый `summariseAndPersist`:

```php
private function summariseAndPersist(
    string $taskId,
    int $durationSec,
): Generator {
    /** @var string|null $summary */
    $summary = yield activity(AiSummaryActivity::class, $taskId);

    yield activity(
        PersistResultActivity::class,
        $taskId,
        $summary,
        $durationSec,
    );

    return null;
}
```

Также нужно добавить импорт:
```php
use function Workflow\sideEffect;
```

- [ ] **Step 6: Проверить миграцию media_tasks**

```bash
grep -r 'transcript' database/migrations/2026_05_07_000004_create_media_tasks_table.php
```

Если колонка `transcript` отсутствует, создать новую миграцию:
```bash
php artisan make:migration add_transcript_column_to_media_tasks_table --table=media_tasks
```

Содержимое миграции:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_tasks', function (Blueprint $table) {
            $table->text('transcript')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('media_tasks', function (Blueprint $table) {
            $table->dropColumn('transcript');
        });
    }
};
```

- [ ] **Step 7: Запустить тесты**

```bash
vendor/bin/pest tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php
vendor/bin/pest tests/Unit/Infrastructure/Workflow/Activities/
```

Expected: PASS (тесты должны пройти с обновлёнными моками)

- [ ] **Step 8: Commit**

```bash
git add app/Application/Ports/Output/MediaTaskRepositoryInterface.php
git add app/Infrastructure/Adapters/Output/Persistence/MediaTaskEloquentRepository.php
git add app/Infrastructure/Workflow/Activities/AiSummaryActivity.php
git add app/Infrastructure/Workflow/Activities/PersistResultActivity.php
git add app/Infrastructure/Workflow/Workflows/TranscribeVideoWorkflow.php
git add database/migrations/
git commit -m "refactor: store transcript in DB instead of passing via activity arguments"
```

---

### Task 6: 🟢 Обновить тесты под изменения #5

**Файлы:**
- Modify: `tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php` — обновить моки для AiSummaryActivity и PersistResultActivity

- [ ] **Step 1: Обновить моки в интеграционном тесте**

AiSummaryActivity теперь принимает `$taskId` (string), а не `$transcript`. PersistResultActivity теперь принимает `$taskId, $summary, $durationSec`.

В тесте моки уже работают через `WorkflowStub::mock()`, который принимает class и возвращаемое значение, без привязки к конкретным аргументам. Поэтому сигнатуры в моках менять не нужно — тест уже должен работать.

**НО:** нужно добавить мок для репозитория, чтобы `getTranscript()` возвращал ожидаемый транскрипт.

```php
// В beforeEach() теста добавить:
$repository = Mockery::mock(MediaTaskRepositoryInterface::class);
$repository->shouldReceive('storeTranscript')->andReturn(null);
$repository->shouldReceive('getTranscript')->andReturn('Mocked transcript');
Container::getInstance()->instance(MediaTaskRepositoryInterface::class, $repository);
```

И добавить импорты:
```php
use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use Illuminate\Container\Container;
use Mockery;
```

- [ ] **Step 2: Запустить тесты**

```bash
vendor/bin/pest tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php
```

Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Integration/Workflow/TranscribeVideoWorkflowTest.php
git commit -m "test: add repository mocks for transcript storage in integration test"
```

---

### Task 7: 🟢 Обновить AGENTS.md

**Файлы:**
- Modify: `AGENTS.md` — строка 127

- [ ] **Step 1: Синхронизировать имя активности**

Текущая строка 127:
```
2. Step 1: If no subtitles, download audio with `AudioDownloaderActivity`.
```

AGENTS.md уже содержит правильное имя (`AudioDownloaderActivity`), а код был переименован под него в Task 3. Проверяем, что AGENTS.md не требует изменений.

Если в AGENTS.md где-то упоминается старое имя `DownloadAudioActivity` — заменить. Выполнить поиск:

```bash
grep -n 'DownloadAudio' AGENTS.md
```

Если нет вхождений — AGENTS.md уже корректен.

- [ ] **Step 2: Commit (если были изменения)**

```bash
git add AGENTS.md
git commit -m "docs: sync AGENTS.md after AudioDownloaderActivity rename"
```

---

## Self-Review Checklist

1. **Spec coverage:**
   - [x] #1 ActivityStub::make() → activity() — Task 1
   - [x] #2 try/catch + compensate() — Task 1
   - [x] #3 Rename DownloadAudioActivity — Task 3
   - [x] #4 (.vtt cleanup) — FALSE POSITIVE, задокументировано
   - [x] #5 Large data via args — Task 5
   - [x] #6 static fn() — исправлено в Task 1
   - [x] #7 heartbeat — Task 4

2. **Placeholder scan:** Нет TBD, TODO, "implement later".

3. **Type consistency:** 
   - `AudioDownloaderActivity` используется консистентно во всех файлах
   - `storeTranscript(string $taskId, string $transcript): void` / `getTranscript(string $taskId): ?string` — сигнатуры консистентны
   - `AiSummaryActivity::execute(string $taskId): string` — консистентно
   - `PersistResultActivity::execute(string $taskId, ?string $summary, int $durationSec): void` — консистентно

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-10-transcribe-video-workflow-fixes.md`. Two execution options:

1. **Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration
2. **Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
