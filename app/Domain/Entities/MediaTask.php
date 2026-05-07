<?php

declare(strict_types=1);

namespace App\Domain\Entities;

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
    private ?string $summary = null;
    private ?string $errorMessage = null;
    private ?DateTimeImmutable $completedAt = null;
    private ?DateTimeImmutable $failedAt = null;
    private ?int $durationSec = null;

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

    public function complete(string $transcript, ?string $summary, int $durationSec): void
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

    public function summary(): ?string
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
