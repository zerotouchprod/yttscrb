<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\SummaryResult;
use App\Domain\ValueObjects\TranscriptionStatus;
use App\Domain\ValueObjects\TranscriptionText;
use App\Domain\ValueObjects\YouTubeUrl;
use DateTimeImmutable;
use LogicException;

final class MediaTask
{
    private TranscriptionStatus $status;
    private ?string $workflowId = null;
    private ?TranscriptionText $resultText = null;
    private ?SummaryResult $summary = null;
    private ?string $errorMessage = null;
    private ?DateTimeImmutable $completedAt = null;
    private ?DateTimeImmutable $failedAt = null;
    private ?int $durationSec = null;
    private ?string $title = null;
    private ?string $slug = null;
    private ?DateTimeImmutable $dmcaRemovedAt = null;
    private ?string $channelName = null;
    private ?string $channelSlug = null;

    private function __construct(
        private readonly string $id,
        private readonly YouTubeUrl $youtubeUrl,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->status = TranscriptionStatus::Pending;
    }

    public static function create(string $id, YouTubeUrl $youtubeUrl): self
    {
        return new self($id, $youtubeUrl, new DateTimeImmutable());
    }

    public function startProcessing(string $workflowId): void
    {
        $this->transitionTo(TranscriptionStatus::Processing);
        $this->workflowId = $workflowId;
    }

    /**
     * Update the workflow ID without state transition.
     * Used when the task is already in processing state and the real workflow ID
     * becomes available after the workflow has started.
     *
     * @throws LogicException if the task is not in processing state.
     */
    public function setWorkflowId(string $workflowId): void
    {
        if ($this->status !== TranscriptionStatus::Processing) {
            throw new LogicException(
                'Cannot set workflow ID on a task that is not in processing state. ' .
                'Current status: ' . $this->status->value,
            );
        }

        $this->workflowId = $workflowId;
    }

    public function complete(string $transcript, ?SummaryResult $summary, int $durationSec): void
    {
        $this->transitionTo(TranscriptionStatus::Completed);
        $this->resultText = new TranscriptionText($transcript);
        $this->summary = $summary;
        $this->durationSec = $durationSec;
        $this->completedAt = new DateTimeImmutable();
        $this->failedAt = null;
        $this->errorMessage = null;
    }

    public function fail(string $reason): void
    {
        $this->transitionTo(TranscriptionStatus::Failed);
        $this->errorMessage = $reason;
        $this->failedAt = new DateTimeImmutable();
        $this->completedAt = null;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function youtubeUrl(): YouTubeUrl
    {
        return $this->youtubeUrl;
    }

    public function status(): TranscriptionStatus
    {
        return $this->status;
    }

    public function workflowId(): ?string
    {
        return $this->workflowId;
    }

    public function resultText(): ?TranscriptionText
    {
        return $this->resultText;
    }

    public function summary(): ?SummaryResult
    {
        return $this->summary;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function failedAt(): ?DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function durationSec(): ?int
    {
        return $this->durationSec;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function slug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function channelName(): ?string
    {
        return $this->channelName;
    }

    public function setChannelName(string $channelName): void
    {
        $this->channelName = $channelName;
    }

    public function channelSlug(): ?string
    {
        return $this->channelSlug;
    }

    public function setChannelSlug(string $channelSlug): void
    {
        $this->channelSlug = $channelSlug;
    }

    public function isDmcaRemoved(): bool
    {
        return $this->dmcaRemovedAt !== null;
    }

    public function dmcaRemovedAt(): ?DateTimeImmutable
    {
        return $this->dmcaRemovedAt;
    }

    public function removeForDmca(): void
    {
        $this->dmcaRemovedAt = new DateTimeImmutable();
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function transitionTo(TranscriptionStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new LogicException(sprintf(
                'Cannot transition media task from %s to %s.',
                $this->status->value,
                $target->value,
            ));
        }

        $this->status = $target;
    }
}
