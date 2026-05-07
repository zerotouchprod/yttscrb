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
