<?php

declare(strict_types=1);

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\YouTubeUrl;

// ---------------------------------------------------------------------------
// slug()
// ---------------------------------------------------------------------------

it('has null slug by default', function (): void {
    $task = MediaTask::create('task-1', new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ'));

    expect($task->slug())->toBeNull();
});

it('stores a slug via setSlug()', function (): void {
    $task = MediaTask::create('task-1', new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ'));

    $task->setSlug('my-video-slug');

    expect($task->slug())->toBe('my-video-slug');
});

it('allows overwriting the slug', function (): void {
    $task = MediaTask::create('task-1', new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ'));

    $task->setSlug('first-slug');
    $task->setSlug('second-slug');

    expect($task->slug())->toBe('second-slug');
});

// ---------------------------------------------------------------------------
// DMCA removal
// ---------------------------------------------------------------------------

it('is not DMCA-removed by default', function (): void {
    $task = MediaTask::create('task-1', new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ'));

    expect($task->isDmcaRemoved())->toBeFalse()
        ->and($task->dmcaRemovedAt())->toBeNull();
});

it('marks itself as DMCA-removed', function (): void {
    $task = MediaTask::create('task-1', new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ'));

    $before = new DateTimeImmutable();
    $task->removeForDmca();
    $after = new DateTimeImmutable();

    expect($task->isDmcaRemoved())->toBeTrue()
        ->and($task->dmcaRemovedAt())->not->toBeNull()
        ->and($task->dmcaRemovedAt())->toBeGreaterThanOrEqual($before)
        ->and($task->dmcaRemovedAt())->toBeLessThanOrEqual($after);
});

it('can be removed for DMCA multiple times without error', function (): void {
    $task = MediaTask::create('task-1', new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ'));

    $task->removeForDmca();
    $firstTimestamp = $task->dmcaRemovedAt();

    // A second call should simply update the timestamp, not throw.
    $task->removeForDmca();

    $secondTimestamp = $task->dmcaRemovedAt();

    expect($task->isDmcaRemoved())->toBeTrue()
        ->and($secondTimestamp)->not->toBeNull();

    // PHPStan: narrow after null check
    \PHPUnit\Framework\Assert::assertNotNull($firstTimestamp);
    \PHPUnit\Framework\Assert::assertNotNull($secondTimestamp);

    expect($secondTimestamp->getTimestamp())->toBeGreaterThanOrEqual($firstTimestamp->getTimestamp());
});
