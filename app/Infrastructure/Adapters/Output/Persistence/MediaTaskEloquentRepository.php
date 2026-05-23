<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Persistence;

use App\Domain\ValueObjects\SummaryResult;
use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\TranscriptionStatus;
use App\Domain\ValueObjects\TranscriptionText;
use App\Domain\ValueObjects\VideoId;
use App\Domain\ValueObjects\YouTubeUrl;
use DateTimeImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use ReflectionProperty;

final class MediaTaskEloquentRepository implements MediaTaskRepositoryInterface
{
    public function save(MediaTask $mediaTask): void
    {
        // Prevent duplicate completed records for the same video
        // (partial unique index idx_media_tasks_video_completed enforces one completed row per video_id)
        if ($mediaTask->status() === TranscriptionStatus::Completed) {
            $existingCompleted = MediaTaskModel::query()
                ->where('video_id', $mediaTask->youtubeUrl()->videoId()->value())
                ->where('status', TranscriptionStatus::Completed->value)
                ->where('id', '!=', $mediaTask->id())
                ->exists();

            if ($existingCompleted) {
                return;
            }
        }

        $data = $this->toArray($mediaTask);

        // Auto-generate slug when task completes and has a title but no slug yet.
        if (
            $mediaTask->status() === TranscriptionStatus::Completed
            && $mediaTask->title() !== null
            && $mediaTask->slug() === null
        ) {
            $slug = $this->generateUniqueSlug($mediaTask->title(), $mediaTask->id());
            $data['slug'] = $slug;
            $mediaTask->setSlug($slug);
        } else {
            $data['slug'] = $mediaTask->slug();
        }

        MediaTaskModel::query()->updateOrCreate(
            ['id' => $mediaTask->id()],
            $data,
        );
    }

    public function saveUserIdentifier(string $taskId, string $userIdentifier): void
    {
        MediaTaskModel::query()->where('id', $taskId)->update([
            'user_identifier' => $userIdentifier,
        ]);
    }

    public function findById(string $id): ?MediaTask
    {
        $model = MediaTaskModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findBySlug(string $slug): ?MediaTask
    {
        /** @var MediaTaskModel|null $model */
        $model = MediaTaskModel::query()
            ->where('slug', $slug)
            ->where('status', TranscriptionStatus::Completed->value)
            ->whereNull('dmca_removed_at')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findCompletedByVideoId(VideoId $videoId): ?MediaTask
    {
        /** @var MediaTaskModel|null $model */
        $model = MediaTaskModel::query()
            ->where('video_id', $videoId->value())
            ->where('status', 'completed')
            ->whereNull('dmca_removed_at')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * Public-facing paginated listing. Excludes DMCA-removed tasks.
     *
     * @return LengthAwarePaginator<int, MediaTask>
     */
    public function findAllPaginated(?string $status, int $perPage, int $page): LengthAwarePaginator
    {
        $query = MediaTaskModel::query()
            ->whereNull('dmca_removed_at')
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, MediaTaskModel> $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /** @var \Illuminate\Support\Collection<int, MediaTask> $entities */
        $entities = $paginator->getCollection()->map(function (mixed $item): MediaTask {
            /** @var MediaTaskModel $model */
            $model = $item;

            return $this->toEntity($model);
        });

        /** @var LengthAwarePaginator<int, MediaTask> */
        return new LengthAwarePaginator(
            $entities,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
        );
    }

    public function findPublicCompletedPaginated(int $perPage, int $page): LengthAwarePaginator
    {
        $query = MediaTaskModel::query()
            ->where('status', TranscriptionStatus::Completed->value)
            ->whereNotNull('title')
            ->whereNull('dmca_removed_at')
            ->orderByDesc('created_at');

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, MediaTaskModel> $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /** @var \Illuminate\Support\Collection<int, MediaTask> $entities */
        $entities = $paginator->getCollection()->map(function (mixed $item): MediaTask {
            /** @var MediaTaskModel $model */
            $model = $item;

            return $this->toEntity($model);
        });

        /** @var LengthAwarePaginator<int, MediaTask> */
        return new LengthAwarePaginator(
            $entities,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
        );
    }

    public function findLatestCompleted(): ?MediaTask
    {
        /** @var MediaTaskModel|null $model */
        $model = MediaTaskModel::query()
            ->where('status', TranscriptionStatus::Completed->value)
            ->whereNull('dmca_removed_at')
            ->orderByDesc('created_at')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function countCompletedSince(DateTimeImmutable $since, ?string $userIdentifier = null): int
    {
        $query = MediaTaskModel::query()
            ->where('status', TranscriptionStatus::Completed->value)
            ->where('completed_at', '>=', $since);

        if ($userIdentifier !== null) {
            $query->where('user_identifier', $userIdentifier);
        }

        return $query->count();
    }

    public function storeTranscript(string $taskId, string $transcript): void
    {
        MediaTaskModel::query()->where('id', $taskId)->update([
            'result_text' => $transcript,
        ]);
    }

    public function getTranscript(string $taskId): ?string
    {
        /** @var MediaTaskModel|null $model */
        $model = MediaTaskModel::query()->find($taskId);

        return $model?->result_text;
    }

    public function storeTitle(string $taskId, string $title): void
    {
        MediaTaskModel::query()->where('id', $taskId)->update([
            'title' => $title,
        ]);
    }

    /**
     * @return LazyCollection<int, array{slug: string, completed_at: string|null, updated_at: string|null}>
     */
    public function findPublicSlugs(): LazyCollection
    {
        /** @var LazyCollection<int, MediaTaskModel> $cursor */
        $cursor = MediaTaskModel::query()
            ->where('status', TranscriptionStatus::Completed->value)
            ->whereNotNull('slug')
            ->whereNull('dmca_removed_at')
            ->orderByDesc('completed_at')
            ->cursor();

        /** @var LazyCollection<int, array{slug: string, completed_at: string|null, updated_at: string|null}> */
        return $cursor->map(static function (MediaTaskModel $model): array {
            return [
                'slug'         => (string) $model->slug,
                'completed_at' => $model->completed_at?->toIso8601String(),
                'updated_at'   => $model->updated_at?->toIso8601String(),
            ];
        });
    }

    /**
     * @return LengthAwarePaginator<int, MediaTask>
     */
    public function searchByTitle(string $query, int $perPage, int $page): LengthAwarePaginator
    {
        $escaped = addcslashes($query, '%_\\');

        $driver = DB::getDriverName();

        $queryBuilder = MediaTaskModel::query()
            ->where('status', TranscriptionStatus::Completed->value)
            ->whereNull('dmca_removed_at')
            ->orderByDesc('created_at');

        if ($driver === 'pgsql') {
            $queryBuilder->whereRaw('title ILIKE ?', ['%' . $escaped . '%']);
        } else {
            // SQLite / other drivers: fall back to LOWER/LIKE
            $queryBuilder->whereRaw('LOWER(title) LIKE ?', ['%' . mb_strtolower($escaped) . '%']);
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, MediaTaskModel> $paginator */
        $paginator = $queryBuilder->paginate($perPage, ['*'], 'page', $page);

        /** @var \Illuminate\Support\Collection<int, MediaTask> $entities */
        $entities = $paginator->getCollection()->map(function (mixed $item): MediaTask {
            /** @var MediaTaskModel $model */
            $model = $item;

            return $this->toEntity($model);
        });

        /** @var LengthAwarePaginator<int, MediaTask> */
        return new LengthAwarePaginator(
            $entities,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
        );
    }

    public function findSimilar(string $taskId, int $limit = 5): array
    {
        $task = $this->findById($taskId);

        if ($task === null || $task->resultText() === null) {
            return [];
        }

        $currentText = $task->resultText()->value();

        return MediaTaskModel::query()
            ->where('status', 'completed')
            ->where('id', '!=', $taskId)
            ->whereNotNull('result_text')
            ->whereNull('dmca_removed_at')
            ->select('id as task_id', 'video_id', 'title', 'slug')
            ->selectRaw('similarity(result_text, ?) as sim', [$currentText])
            ->whereRaw('similarity(result_text, ?) > 0.05', [$currentText])
            ->orderBy('sim', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'task_id'    => $row->task_id,
                'video_id'   => $row->video_id,
                'title'      => $row->title,
                'slug'       => $row->slug,
                'similarity' => round((float) $row->sim, 4),
            ])
            ->all();
    }

    private function generateUniqueSlug(string $title, string $taskId): string
    {
        $base = Str::slug($title);

        if (! MediaTaskModel::query()->where('slug', $base)->exists()) {
            return $base;
        }

        $suffix = substr(str_replace('-', '', $taskId), 0, 6);
        $candidate = $base . '-' . strtolower($suffix);

        if (! MediaTaskModel::query()->where('slug', $candidate)->exists()) {
            return $candidate;
        }

        // Final deterministic fallback using the full task UUID suffix.
        return $base . '-' . strtolower(substr(str_replace('-', '', $taskId), 0, 12));
    }

    private function toEntity(MediaTaskModel $model): MediaTask
    {
        $task = MediaTask::create(
            $model->id,
            new YouTubeUrl($model->youtube_url),
        );

        $this->setPrivate($task, 'status', TranscriptionStatus::from($model->status));
        $this->setPrivate($task, 'workflowId', $model->workflow_id);

        if ($model->summary !== null) {
            /** @var array{introduction: string, key_points: array<int, array{timecode: string, title: string, details: string}>, conclusion?: string|null, resources?: array<int, array{type: string, name: string, url?: string|null}>, clickbait_verdict?: array{score: int, comment: string}|null, tutorial_steps?: array<int, array{step: int, time: string, action: string}>} $summaryData */
            $summaryData = $model->summary;
            $this->setPrivate($task, 'summary', SummaryResult::fromArray($summaryData));
        }
        $this->setPrivate($task, 'errorMessage', $model->error_message);
        $this->setPrivate($task, 'durationSec', $model->duration_sec);
        $this->setPrivate($task, 'slug', $model->slug);

        if ($model->title !== null) {
            $task->setTitle($model->title);
        }

        if ($model->result_text !== null) {
            $this->setPrivate($task, 'resultText', new TranscriptionText($model->result_text));
        }

        if ($model->completed_at !== null) {
            $this->setPrivate($task, 'completedAt', new DateTimeImmutable($model->completed_at->toIso8601String()));
        }

        if ($model->failed_at !== null) {
            $this->setPrivate($task, 'failedAt', new DateTimeImmutable($model->failed_at->toIso8601String()));
        }

        if ($model->dmca_removed_at !== null) {
            $dmcaDate = new DateTimeImmutable($model->dmca_removed_at->toIso8601String());
            $this->setPrivate($task, 'dmcaRemovedAt', $dmcaDate);
        }

        return $task;
    }

    /**
     * @return array<string, int|string|array<string, mixed>|null|\DateTimeImmutable>
     */
    private function toArray(MediaTask $task): array
    {
        $videoId = $task->isDmcaRemoved()
            ? null
            : $task->youtubeUrl()->videoId()->value();

        return [
            'youtube_url'     => $task->youtubeUrl()->value(),
            'video_id'        => $videoId,
            'status'          => $task->status()->value,
            'workflow_id'     => $task->workflowId(),
            'result_text'     => $task->resultText()?->value(),
            'summary'         => $task->summary()?->toArray(),
            'duration_sec'    => $task->durationSec(),
            'title'           => $task->title(),
            'error_message'   => $task->errorMessage(),
            'completed_at'    => $task->completedAt(),
            'failed_at'       => $task->failedAt(),
            'dmca_removed_at' => $task->dmcaRemovedAt(),
        ];
    }

    private function setPrivate(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
