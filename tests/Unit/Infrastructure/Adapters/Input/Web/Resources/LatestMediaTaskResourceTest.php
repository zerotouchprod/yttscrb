<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\SummaryKeyPoint;
use App\Domain\ValueObjects\SummaryResult;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Input\Web\Resources\LatestMediaTaskResource;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class LatestMediaTaskResourceTest extends TestCase
{
    public function testLatestCompletedTaskHasNoVideoId(): void
    {
        $summary = new SummaryResult(
            introduction: 'Intro',
            keyPoints: [
                new SummaryKeyPoint('00:01:00', 'Point', 'Details'),
            ],
            conclusion: 'Conclusion',
        );

        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->startProcessing("wf-test"); $task->complete('Full transcript', $summary, 120);

        $resource = new LatestMediaTaskResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        $this->assertArrayNotHasKey('video_id', $result);
        $this->assertSame($task->id(), $result['task_id']);
        $this->assertSame('completed', $result['status']);
        /** @var array<string, mixed> $resultData */
        $resultData = $result['result'];
        // resultData already typed as array via @var
        /** @var array<string, mixed> $summaryData */
        $summaryData = $resultData['summary'];
        // summaryData already typed as array via @var
        $this->assertSame('Intro', $summaryData['introduction']);
    }

    public function testLatestTaskWithNullSummary(): void
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->startProcessing("wf-test"); $task->complete('Transcript only, no AI summary', null, 90);

        $resource = new LatestMediaTaskResource($task);
        /** @var array<string, mixed> $result */
        $result = $resource->toArray(new Request());

        $this->assertArrayNotHasKey('video_id', $result);
        /** @var array<string, mixed> $resultData */
        $resultData = $result['result'];
        $this->assertNull($resultData['summary']);
        $this->assertSame('Transcript only, no AI summary', $resultData['transcript']);
    }
}
