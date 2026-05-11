<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Seo;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Repository-level tests for slug and DMCA filtering.
 * Verifies that findBySlug, findLatestCompleted, and findCompletedByVideoId
 * all respect the DMCA exclusion rule.
 */
final class RepositoryDmcaFilterTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // findBySlug
    // -----------------------------------------------------------------------

    public function testFindBySlugReturnsCompletedTask(): void
    {
        MediaTaskModel::query()->create($this->taskData('slug-a', 'completed'));

        $repo = $this->app->make(MediaTaskRepositoryInterface::class);
        $result = $repo->findBySlug('slug-a');

        self::assertNotNull($result);
        self::assertSame('slug-a', $result->slug());
    }

    public function testFindBySlugReturnsNullForDmcaRemoved(): void
    {
        MediaTaskModel::query()->create(array_merge(
            $this->taskData('slug-b', 'completed'),
            ['dmca_removed_at' => now()],
        ));

        $repo = $this->app->make(MediaTaskRepositoryInterface::class);
        self::assertNull($repo->findBySlug('slug-b'));
    }

    public function testFindBySlugReturnsNullForNonCompletedTask(): void
    {
        MediaTaskModel::query()->create($this->taskData('slug-c', 'pending'));

        $repo = $this->app->make(MediaTaskRepositoryInterface::class);
        self::assertNull($repo->findBySlug('slug-c'));
    }

    // -----------------------------------------------------------------------
    // findLatestCompleted
    // -----------------------------------------------------------------------

    public function testFindLatestCompletedSkipsDmcaRemoved(): void
    {
        // Older task — not removed
        MediaTaskModel::query()->create(array_merge(
            $this->taskData('slug-d', 'completed', 'aaaaaaaaa01'),
            ['completed_at' => now()->subHour()],
        ));

        // Newer task — DMCA removed
        MediaTaskModel::query()->create(array_merge(
            $this->taskData('slug-e', 'completed', 'aaaaaaaaa02'),
            [
                'id'              => 'eeeeeeee-0000-0000-0000-000000000002',
                'youtube_url'     => 'https://youtube.com/watch?v=aaaaaaaaa02',
                'video_id'        => 'aaaaaaaaa02',
                'completed_at'    => now(),
                'dmca_removed_at' => now(),
            ],
        ));

        $repo = $this->app->make(MediaTaskRepositoryInterface::class);
        $result = $repo->findLatestCompleted();

        self::assertNotNull($result);
        self::assertSame('slug-d', $result->slug());
    }

    // -----------------------------------------------------------------------
    // findCompletedByVideoId
    // -----------------------------------------------------------------------

    public function testFindCompletedByVideoIdSkipsDmcaRemoved(): void
    {
        MediaTaskModel::query()->create(array_merge(
            $this->taskData('slug-f', 'completed', 'aaaaaaaaa03'),
            ['dmca_removed_at' => now()],
        ));

        $repo = $this->app->make(MediaTaskRepositoryInterface::class);
        $result = $repo->findCompletedByVideoId(new \App\Domain\ValueObjects\VideoId('aaaaaaaaa03'));

        self::assertNull($result);
    }

    public function testFindCompletedByVideoIdReturnsNonRemovedTask(): void
    {
        MediaTaskModel::query()->create($this->taskData('slug-g', 'completed', 'aaaaaaaaa04'));

        $repo = $this->app->make(MediaTaskRepositoryInterface::class);
        $result = $repo->findCompletedByVideoId(new \App\Domain\ValueObjects\VideoId('aaaaaaaaa04'));

        self::assertNotNull($result);
    }

    // -----------------------------------------------------------------------
    // findAllPaginated (DMCA filter on public history)
    // -----------------------------------------------------------------------

    public function testFindAllPaginatedSkipsDmcaRemoved(): void
    {
        MediaTaskModel::query()->create($this->taskData('slug-h', 'completed', 'aaaaaaaaa05'));
        MediaTaskModel::query()->create(array_merge(
            $this->taskData('slug-i', 'completed', 'aaaaaaaaa06'),
            [
                'id'              => 'eeeeeeee-0000-0000-0000-000000000006',
                'youtube_url'     => 'https://youtube.com/watch?v=aaaaaaaaa06',
                'video_id'        => 'aaaaaaaaa06',
                'dmca_removed_at' => now(),
            ],
        ));

        $repo = $this->app->make(MediaTaskRepositoryInterface::class);
        $paginator = $repo->findAllPaginated(null, 10, 1);

        self::assertSame(1, $paginator->total());
        self::assertSame('slug-h', $paginator->getCollection()->first()?->slug());
    }

    // -----------------------------------------------------------------------
    // findPublicCompletedPaginated
    // -----------------------------------------------------------------------

    public function testFindPublicCompletedPaginatedReturnsOnlyCompletedWithTitleNotDmca(): void
    {
        // Non-completed — excluded
        MediaTaskModel::query()->create($this->taskData('slug-j', 'processing', 'aaaaaaaaa07'));
        // Completed but no title — excluded
        MediaTaskModel::query()->create(array_merge(
            $this->taskData('slug-k', 'completed', 'aaaaaaaaa08'),
            ['title' => null, 'slug' => 'slug-k'],
        ));
        // Completed with DMCA — excluded
        MediaTaskModel::query()->create(array_merge(
            $this->taskData('slug-l', 'completed', 'aaaaaaaaa09'),
            [
                'id'              => 'eeeeeeee-0000-0000-0000-000000000009',
                'youtube_url'     => 'https://youtube.com/watch?v=aaaaaaaaa09',
                'video_id'        => 'aaaaaaaaa09',
                'dmca_removed_at' => now(),
            ],
        ));
        // Valid public task — included
        MediaTaskModel::query()->create($this->taskData('slug-m', 'completed', 'aaaaaaaaa10'));

        $repo = $this->app->make(MediaTaskRepositoryInterface::class);
        $paginator = $repo->findPublicCompletedPaginated(10, 1);

        self::assertSame(1, $paginator->total(), 'Only completed, titled, non-DMCA tasks should appear.');
        self::assertSame('slug-m', $paginator->getCollection()->first()?->slug());
    }

    public function testFindPublicCompletedPaginatedOrdersNewestFirst(): void
    {
        $older = new \Carbon\CarbonImmutable('2026-01-01 12:00:00');
        $newer = new \Carbon\CarbonImmutable('2026-06-01 12:00:00');

        \Illuminate\Support\Facades\DB::table('media_tasks')->insert(array_merge(
            $this->taskData('slug-n', 'completed', 'aaaaaaaaa11'),
            [
                'summary'     => json_encode(['introduction' => 'Summary.', 'key_points' => [], 'conclusion' => null]),
                'created_at'  => $older,
                'updated_at'  => $older,
            ],
        ));
        \Illuminate\Support\Facades\DB::table('media_tasks')->insert(array_merge(
            $this->taskData('slug-o', 'completed', 'aaaaaaaaa12'),
            [
                'id'          => 'eeeeeeee-0000-0000-0000-000000000012',
                'youtube_url' => 'https://youtube.com/watch?v=aaaaaaaaa12',
                'video_id'    => 'aaaaaaaaa12',
                'summary'     => json_encode(['introduction' => 'Summary.', 'key_points' => [], 'conclusion' => null]),
                'created_at'  => $newer,
                'updated_at'  => $newer,
            ],
        ));

        $repo = $this->app->make(MediaTaskRepositoryInterface::class);
        $paginator = $repo->findPublicCompletedPaginated(10, 1);

        self::assertSame(2, $paginator->total());
        // Newest first
        self::assertSame('slug-o', $paginator->getCollection()->first()?->slug());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function taskData(string $slug, string $status, string $videoId = 'dQw4w9WgXcQ'): array
    {
        return [

            'id'           => 'eeeeeeee-0000-0000-0000-' . str_pad(substr(md5($slug), 0, 12), 12, '0'),
            'youtube_url'  => "https://youtube.com/watch?v={$videoId}",
            'video_id'     => $videoId,
            'title'        => 'Test Video ' . $slug,
            'slug'         => $slug,
            'status'       => $status,
            'result_text'  => 'Transcript.',
            'summary'      => ['introduction' => 'Summary.', 'key_points' => [], 'conclusion' => null],
            'duration_sec' => 60,
            'completed_at' => $status === 'completed' ? now() : null,
        ];
    }
}
