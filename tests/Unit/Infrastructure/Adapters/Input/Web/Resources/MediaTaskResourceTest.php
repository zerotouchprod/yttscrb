<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\SummaryKeyPoint;
use App\Domain\ValueObjects\SummaryResult;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Input\Web\Resources\MediaTaskResource;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class MediaTaskResourceTest extends TestCase
{
    public function testPendingTaskReturnsOnlyBasicFields(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );

        $resource = new MediaTaskResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        $this->assertSame($task->id(), $result['task_id']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $result['youtube_url']);
        $this->assertSame('dQw4w9WgXcQ', $result['video_id']);
        $this->assertNull($result['title']);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('_links', $result);
        /** @var array<string, mixed> $links */
        $links = $result['_links'];
        $this->assertArrayHasKey('self', $links);
        $this->assertArrayNotHasKey('result', $result);
        $this->assertArrayNotHasKey('duration_sec', $result);
        $this->assertArrayNotHasKey('error_message', $result);
    }

    public function testProcessingTaskAddsEstimatedCompletionSec(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->startProcessing('wf-123');

        $resource = new MediaTaskResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        $this->assertSame('processing', $result['status']);
        $this->assertSame(90, $result['estimated_completion_sec']);
        $this->assertArrayNotHasKey('result', $result);
    }

    public function testCompletedTaskWithoutSlugHasNoPublicPage(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->startProcessing("wf-test"); $task->complete('Transcript text', null, 120);

        $resource = new MediaTaskResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        $this->assertSame('completed', $result['status']);
        $this->assertArrayHasKey('result', $result);
        /** @var array<string, mixed> $links */
        $links = $result['_links'];
        $this->assertArrayHasKey('download_txt', $links);
        $this->assertArrayNotHasKey('public_page', $links);
    }

    public function testCompletedTaskWithSlugAndNoDmcaHasPublicPage(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->setTitle('Test Video');
        $task->setSlug('test-video');
        $task->startProcessing("wf-test"); $task->complete('Transcript text', null, 120);

        $resource = new MediaTaskResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        /** @var array<string, mixed> $links */
        $links = $result['_links'];
        $this->assertSame('/v/test-video', $links['public_page']);
    }

    public function testCompletedTaskWithDmcaHasNoPublicPage(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->setTitle('Test Video');
        $task->setSlug('test-video');
        $task->startProcessing("wf-test"); $task->complete('Transcript text', null, 120);
        $task->removeForDmca();

        $resource = new MediaTaskResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        /** @var array<string, mixed> $links */
        $links = $result['_links'];
        $this->assertArrayNotHasKey('public_page', $links);
    }

    public function testCompletedTaskWithoutSummaryReturnsNullSummary(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->startProcessing("wf-test"); $task->complete('Transcript text', null, 120);

        $resource = new MediaTaskResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        /** @var array<string, mixed> $resultData */
        $resultData = $result['result'];
        $this->assertNull($resultData['summary']);
    }

    public function testCompletedTaskWithSummaryReturnsCorrectNestedStructure(): void
    {
        $summary = new SummaryResult(
            introduction: 'Intro text',
            keyPoints: [
                new SummaryKeyPoint('00:01:00', 'Key point 1', 'Details 1'),
            ],
            conclusion: 'Conclusion text',
        );

        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->startProcessing("wf-test"); $task->complete('Transcript text', $summary, 120);

        $resource = new MediaTaskResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        /** @var array<string, mixed> $resultData */
        $resultData = $result['result'];
        $this->assertIsArray($resultData['summary']);
        /** @var array<string, mixed> $summaryData */
        $summaryData = $resultData['summary'];
        $this->assertSame('Intro text', $summaryData['introduction']);
        /** @var array<int, mixed> $keyPoints */
        $keyPoints = $summaryData['key_points'];
        $this->assertCount(1, $keyPoints);
        $this->assertSame('Conclusion text', $summaryData['conclusion']);
    }

    public function testFailedTaskReturnsErrorMessageAndFailedAt(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->startProcessing("wf-test"); $task->fail('Download timeout');

        $resource = new MediaTaskResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Download timeout', $result['error_message']);
        $this->assertArrayHasKey('failed_at', $result);
        $this->assertArrayNotHasKey('result', $result);
    }
}
