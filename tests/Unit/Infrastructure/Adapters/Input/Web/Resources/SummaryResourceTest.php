<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\ValueObjects\SummaryKeyPoint;
use App\Domain\ValueObjects\SummaryResult;
use App\Infrastructure\Adapters\Input\Web\Resources\SummaryResource;
use Illuminate\Http\Request;
use Tests\TestCase;

final class SummaryResourceTest extends TestCase
{
    public function testSummaryResourceWithKeyPointsReturnsCorrectStructure(): void
    {
        $summary = new SummaryResult(
            introduction: 'This video explains...',
            keyPoints: [
                new SummaryKeyPoint(
                    timecode: '00:02:30',
                    title: 'Introduction to the topic',
                    details: 'The speaker begins by introducing the main concepts.',
                ),
                new SummaryKeyPoint(
                    timecode: '00:10:00',
                    title: 'Deep dive',
                    details: 'A detailed explanation of the core mechanism.',
                ),
            ],
            conclusion: 'Overall, this is a great overview.',
        );

        $resource = new SummaryResource($summary);
        $result = $resource->toArray(new Request());

        $this->assertSame('This video explains...', $result['introduction']);
        $this->assertCount(2, $result['key_points']);
        $this->assertSame('00:02:30', $result['key_points'][0]['timecode']);
        $this->assertSame('Introduction to the topic', $result['key_points'][0]['title']);
        $this->assertSame('The speaker begins by introducing the main concepts.', $result['key_points'][0]['details']);
        $this->assertSame('00:10:00', $result['key_points'][1]['timecode']);
        $this->assertSame('Overall, this is a great overview.', $result['conclusion']);
    }

    public function testSummaryResourceWithNullConclusion(): void
    {
        $summary = new SummaryResult(
            introduction: 'Quick summary.',
            keyPoints: [],
            conclusion: null,
        );

        $resource = new SummaryResource($summary);
        $result = $resource->toArray(new Request());

        $this->assertArrayHasKey('conclusion', $result);
        $this->assertNull($result['conclusion']);
    }

    public function testSummaryResourceWithEmptyKeyPoints(): void
    {
        $summary = new SummaryResult(
            introduction: 'No key points here.',
            keyPoints: [],
            conclusion: 'Done.',
        );

        $resource = new SummaryResource($summary);
        $result = $resource->toArray(new Request());

        /** @var array<int, mixed> $keyPoints */
        $keyPoints = $result['key_points'];
        $this->assertCount(0, $keyPoints);
    }
}
