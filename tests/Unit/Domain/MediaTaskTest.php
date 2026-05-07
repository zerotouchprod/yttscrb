<?php

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\TranscriptionStatus;
use App\Domain\ValueObjects\YouTubeUrl;

it('creates a pending media task', function (): void {
    $task = MediaTask::create('task-1', new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ'));

    expect($task->id())->toBe('task-1')
        ->and($task->status())->toBe(TranscriptionStatus::Pending)
        ->and($task->workflowId())->toBeNull();
});

it('moves a task through processing to completed', function (): void {
    $task = MediaTask::create('task-1', new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ'));

    $task->startProcessing('transcribe-task-1');
    $task->complete('Transcript text', 'Summary text', 120);

    expect($task->status())->toBe(TranscriptionStatus::Completed)
        ->and($task->workflowId())->toBe('transcribe-task-1')
        ->and($task->resultText()?->value())->toBe('Transcript text')
        ->and($task->summary())->toBe('Summary text')
        ->and($task->durationSec())->toBe(120)
        ->and($task->completedAt())->not->toBeNull()
        ->and($task->failedAt())->toBeNull();
});

it('moves a processing task to failed', function (): void {
    $task = MediaTask::create('task-1', new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ'));

    $task->startProcessing('transcribe-task-1');
    $task->fail('Video unavailable');

    expect($task->status())->toBe(TranscriptionStatus::Failed)
        ->and($task->errorMessage())->toBe('Video unavailable')
        ->and($task->failedAt())->not->toBeNull()
        ->and($task->completedAt())->toBeNull();
});

it('does not complete before processing starts', function (): void {
    $task = MediaTask::create('task-1', new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ'));

    $task->complete('Transcript text', null, 120);
})->throws(\LogicException::class);
