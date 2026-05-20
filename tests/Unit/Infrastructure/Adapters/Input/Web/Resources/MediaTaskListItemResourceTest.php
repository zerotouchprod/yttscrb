<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Input\Web\Resources\MediaTaskListItemResource;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class MediaTaskListItemResourceTest extends TestCase
{
    public function testPendingTaskHasNoOptionalFields(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );

        $resource = new MediaTaskListItemResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        $this->assertSame('pending', $result['status']);
        $this->assertArrayNotHasKey('completed_at', $result);
        $this->assertArrayNotHasKey('duration_sec', $result);
        /** @var array<string, mixed> $links */
        $links = $result['_links'];
        $this->assertNull($links['public_page']);
    }

    public function testCompletedTaskWithSlugHasPublicPage(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->setTitle('My Video');
        $task->setSlug('my-video');
        $task->startProcessing("wf-test"); $task->complete('Transcript', null, 120);

        $resource = new MediaTaskListItemResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        /** @var array<string, mixed> $links */
        $links = $result['_links'];
        $this->assertSame('/v/my-video', $links['public_page']);
        $this->assertArrayHasKey('completed_at', $result);
    }

    public function testCompletedTaskWithDmcaHasNoPublicPage(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->setTitle('DMCA Video');
        $task->setSlug('dmca-video');
        $task->startProcessing("wf-test"); $task->complete('Transcript', null, 120);
        $task->removeForDmca();

        $resource = new MediaTaskListItemResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        /** @var array<string, mixed> $links */
        $links = $result['_links'];
        $this->assertNull($links['public_page']);
    }
}
