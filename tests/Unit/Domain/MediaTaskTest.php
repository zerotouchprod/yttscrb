<?php

use App\Domain\ValueObjects\SummaryResult;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\SummaryKeyPoint;
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

    $summary = new SummaryResult(
        introduction: 'This video covers testing.',
        keyPoints: [
            new SummaryKeyPoint('01:00', 'Test Setup', 'How to set up tests.'),
        ],
        conclusion: 'Testing is important.',
    );

    $task->startProcessing('transcribe-task-1');
    $task->complete('Transcript text', $summary, 120);

    expect($task->status())->toBe(TranscriptionStatus::Completed)
        ->and($task->workflowId())->toBe('transcribe-task-1')
        ->and($task->resultText()?->value())->toBe('Transcript text')
        ->and($task->summary())->not->toBeNull()
        ->and($task->summary()?->introduction())->toBe('This video covers testing.')
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

it('has null title by default', function (): void {
    $task = MediaTask::create('test-id', new YouTubeUrl('https://youtube.com/watch?v=dQw4w9WgXcQ'));

    expect($task->title())->toBeNull();
});

it('stores title via setTitle', function (): void {
    $task = MediaTask::create('test-id', new YouTubeUrl('https://youtube.com/watch?v=dQw4w9WgXcQ'));
    $task->setTitle('Rick Astley - Never Gonna Give You Up');

    expect($task->title())->toBe('Rick Astley - Never Gonna Give You Up');
});

it('title persists after complete', function (): void {
    $task = MediaTask::create('test-id', new YouTubeUrl('https://youtube.com/watch?v=dQw4w9WgXcQ'));
    $task->startProcessing('wf-123');
    $task->setTitle('Test Video Title');

    $summary = new SummaryResult(
        introduction: 'A test summary.',
        keyPoints: [],
    );

    $task->complete('transcript text', $summary, 180);

    expect($task->title())->toBe('Test Video Title')
        ->and($task->status())->toBe(TranscriptionStatus::Completed);
});
