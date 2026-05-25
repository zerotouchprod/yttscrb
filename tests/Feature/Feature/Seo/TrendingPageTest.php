<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Seo;

use App\Application\Ports\Output\ViewTrackerInterface;
use App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRedisViewTracker;
use Tests\TestCase;

/**
 * Feature tests for GET /trending.
 */
final class TrendingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Default: no trending data in Redis → falls back to DB.
        $tracker = new FakeRedisViewTracker(isRecentlyViewed: false);
        $this->app->instance(ViewTrackerInterface::class, $tracker);
    }

    public function testTrendingPageReturns200(): void
    {
        $this->get('/trending')->assertOk();
    }

    public function testTrendingPageShowsEmptyStateWhenNoTasks(): void
    {
        $this->get('/trending')
            ->assertOk()
            ->assertSee('No trending data yet');
    }

    public function testTrendingPageListsCompletedTasksByViewCount(): void
    {
        MediaTaskModel::query()->create([
            'id'           => 'aaaaaaaa-0000-0000-0000-000000000011',
            'youtube_url'  => 'https://youtube.com/watch?v=AAAAAAAAAAA',
            'video_id'     => 'AAAAAAAAAAA',
            'title'        => 'Low Views Video',
            'slug'         => 'low-views-video',
            'status'       => 'completed',
            'views_count'  => 5,
            'result_text'  => 'Low views.',
            'summary'      => ['introduction' => 'Summary.', 'key_points' => [], 'conclusion' => null],
            'duration_sec' => 60,
            'completed_at' => now()->subDays(2),
        ]);

        MediaTaskModel::query()->create([
            'id'           => 'aaaaaaaa-0000-0000-0000-000000000012',
            'youtube_url'  => 'https://youtube.com/watch?v=BBBBBBBBBBB',
            'video_id'     => 'BBBBBBBBBBB',
            'title'        => 'High Views Video',
            'slug'         => 'high-views-video',
            'status'       => 'completed',
            'views_count'  => 200,
            'result_text'  => 'High views.',
            'summary'      => ['introduction' => 'Summary.', 'key_points' => [], 'conclusion' => null],
            'duration_sec' => 120,
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->get('/trending');

        $response->assertOk()
            ->assertSee('High Views Video')
            ->assertSee('Low Views Video')
            ->assertSee('Trending This Week');

        // High-views task should appear before low-views task in the DOM.
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertGreaterThan(
            strpos($content, 'High Views Video'),
            strpos($content, 'Low Views Video'),
        );
    }

    public function testTrendingPageExcludesDmcaRemovedTasks(): void
    {
        MediaTaskModel::query()->create([
            'id'              => 'aaaaaaaa-0000-0000-0000-000000000013',
            'youtube_url'     => 'https://youtube.com/watch?v=CCCCCCCCCCC',
            'video_id'        => 'CCCCCCCCCCC',
            'title'           => 'DMCA Removed Video',
            'slug'            => 'dmca-removed-video',
            'status'          => 'completed',
            'views_count'     => 9999,
            'result_text'     => 'Removed.',
            'summary'         => ['introduction' => 'Summary.', 'key_points' => [], 'conclusion' => null],
            'duration_sec'    => 60,
            'completed_at'    => now(),
            'dmca_removed_at' => now(),
        ]);

        $this->get('/trending')
            ->assertOk()
            ->assertDontSee('DMCA Removed Video');
    }

    public function testTrendingPageHasCanonicalUrl(): void
    {
        $this->get('/trending')
            ->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('/trending');
    }

    public function testTrendingPageUsesRedisRankingWhenDataAvailable(): void
    {
        /** @var MediaTaskModel $taskA */
        $taskA = MediaTaskModel::query()->create([
            'id'           => 'aaaaaaaa-0000-0000-0000-000000000014',
            'youtube_url'  => 'https://youtube.com/watch?v=DDDDDDDDDDD',
            'video_id'     => 'DDDDDDDDDDD',
            'title'        => 'DB Low But Redis High',
            'slug'         => 'db-low-redis-high',
            'status'       => 'completed',
            'views_count'  => 1,
            'result_text'  => 'Low DB views.',
            'summary'      => ['introduction' => 'S.', 'key_points' => [], 'conclusion' => null],
            'duration_sec' => 60,
            'completed_at' => now(),
        ]);

        // Override tracker: Redis has data and returns only task A.
        $tracker = new FakeRedisViewTracker(isRecentlyViewed: false);
        $tracker->hasTrendingDataReturn = true;
        $tracker->topTaskIds = [(string) $taskA->id];
        $this->app->instance(ViewTrackerInterface::class, $tracker);

        $this->get('/trending')
            ->assertOk()
            ->assertSee('DB Low But Redis High');
    }
}
