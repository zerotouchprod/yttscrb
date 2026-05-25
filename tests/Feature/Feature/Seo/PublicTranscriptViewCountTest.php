<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Seo;

use App\Application\Ports\Output\ViewTrackerInterface;
use App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel;
use App\Infrastructure\Adapters\Output\Queue\IncrementViewCountJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeRedisViewTracker;
use Tests\TestCase;

/**
 * Feature tests for view-count tracking on public transcript pages.
 */
final class PublicTranscriptViewCountTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(string $suffix, string $slug): MediaTaskModel
    {
        /** @var MediaTaskModel $model */
        $model = MediaTaskModel::query()->create([
            'id'           => 'bbbbbbbb-0000-0000-0000-' . $suffix,
            'youtube_url'  => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'video_id'     => 'dQw4w9WgXcQ',
            'title'        => 'Test Video',
            'slug'         => $slug,
            'status'       => 'completed',
            'views_count'  => 0,
            'result_text'  => 'Transcript text.',
            'summary'      => ['introduction' => 'Summary.', 'key_points' => [], 'conclusion' => null],
            'duration_sec' => 60,
            'completed_at' => now(),
        ]);

        return $model;
    }

    public function testDispatchesIncrementJobOnFirstView(): void
    {
        Queue::fake();

        // Not yet viewed — tracker reports no existing dedup key.
        $tracker = new FakeRedisViewTracker(isRecentlyViewed: false);
        $this->app->instance(ViewTrackerInterface::class, $tracker);

        $this->makeTask('000000000021', 'first-view-video');

        $this->get('/v/first-view-video')->assertOk();

        Queue::assertPushed(IncrementViewCountJob::class);
        self::assertTrue($tracker->markViewedCalled);
        self::assertTrue($tracker->recordWeeklyViewCalled);
    }

    public function testDoesNotDispatchJobOnRepeatedViewFromSameIp(): void
    {
        Queue::fake();

        // Already viewed — tracker reports existing dedup key.
        $tracker = new FakeRedisViewTracker(isRecentlyViewed: true);
        $this->app->instance(ViewTrackerInterface::class, $tracker);

        $this->makeTask('000000000022', 'repeat-view-video');

        $this->get('/v/repeat-view-video')->assertOk();

        Queue::assertNotPushed(IncrementViewCountJob::class);
        self::assertFalse($tracker->markViewedCalled);
        self::assertFalse($tracker->recordWeeklyViewCalled);
    }
}
