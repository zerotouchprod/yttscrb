<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Persistence;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\TranscriptionStatus;
use App\Domain\ValueObjects\TranscriptionText;
use App\Domain\ValueObjects\VideoId;
use App\Domain\ValueObjects\YouTubeUrl;
use DateTimeImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use ReflectionProperty;

final class MediaTaskEloquentRepository implements MediaTaskRepositoryInterface
{
    public function save(MediaTask $mediaTask): void
    {
        MediaTaskModel::query()->updateOrCreate(
            ['id' => $mediaTask->id()],
            $this->toArray($mediaTask),
        );
    }

    public function findById(string $id): ?MediaTask
    {
        $model = MediaTaskModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findCompletedByVideoId(VideoId $videoId): ?MediaTask
    {
        $model = MediaTaskModel::query()->where('video_id', $videoId->value())
            ->where('status', 'completed')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return LengthAwarePaginator<int, MediaTask>
     */
    public function findAllPaginated(?string $status, int $perPage, int $page): LengthAwarePaginator
    {
        $query = MediaTaskModel::query()->orderByDesc('created_at');

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

    public function findLatestCompleted(): ?MediaTask
    {
        /** @var MediaTaskModel|null $model */
        $model = MediaTaskModel::query()
            ->where('status', TranscriptionStatus::Completed->value)
            ->orderByDesc('created_at')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function countCompletedSince(DateTimeImmutable $since): int
    {
        return MediaTaskModel::query()
            ->where('status', TranscriptionStatus::Completed->value)
            ->where('completed_at', '>=', $since)
            ->count();
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

    private function toEntity(MediaTaskModel $model): MediaTask
    {
        $task = MediaTask::create(
            $model->id,
            new YouTubeUrl($model->youtube_url),
        );

        $this->setPrivate($task, 'status', TranscriptionStatus::from($model->status));
        $this->setPrivate($task, 'workflowId', $model->workflow_id);
        $this->setPrivate($task, 'summary', $model->summary);
        $this->setPrivate($task, 'errorMessage', $model->error_message);
        $this->setPrivate($task, 'durationSec', $model->duration_sec);

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

        return $task;
    }

    /**
     * @return array<string, int|string|null|\DateTimeImmutable>
     */
    private function toArray(MediaTask $task): array
    {
        return [
            'youtube_url' => $task->youtubeUrl()->value(),
            'video_id' => $task->youtubeUrl()->videoId()->value(),
            'status' => $task->status()->value,
            'workflow_id' => $task->workflowId(),
            'result_text' => $task->resultText()?->value(),
            'summary' => $task->summary(),
            'duration_sec' => $task->durationSec(),
            'title' => $task->title(),
            'error_message' => $task->errorMessage(),
            'completed_at' => $task->completedAt(),
            'failed_at' => $task->failedAt(),
        ];
    }

    private function setPrivate(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
