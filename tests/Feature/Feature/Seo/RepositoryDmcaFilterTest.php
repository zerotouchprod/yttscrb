<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Seo;

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

        $repo = $this->app->make(\App\Application\Ports\Output\MediaTaskRepositoryInterface::class);
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

        $repo = $this->app->make(\App\Application\Ports\Output\MediaTaskRepositoryInterface::class);
        self::assertNull($repo->findBySlug('slug-b'));
    }

    public function testFindBySlugReturnsNullForNonCompletedTask(): void
    {
        MediaTaskModel::query()->create($this->taskData('slug-c', 'pending'));

        $repo = $this->app->make(\App\Application\Ports\Output\MediaTaskRepositoryInterface::class);
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

        $repo = $this->app->make(\App\Application\Ports\Output\MediaTaskRepositoryInterface::class);
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

        $repo = $this->app->make(\App\Application\Ports\Output\MediaTaskRepositoryInterface::class);
        $result = $repo->findCompletedByVideoId(new \App\Domain\ValueObjects\VideoId('aaaaaaaaa03'));

        self::assertNull($result);
    }

    public function testFindCompletedByVideoIdReturnsNonRemovedTask(): void
    {
        MediaTaskModel::query()->create($this->taskData('slug-g', 'completed', 'aaaaaaaaa04'));

        $repo = $this->app->make(\App\Application\Ports\Output\MediaTaskRepositoryInterface::class);
        $result = $repo->findCompletedByVideoId(new \App\Domain\ValueObjects\VideoId('aaaaaaaaa04'));

        self::assertNotNull($result);
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
            'id'           => 'eeeeeeee-0000-0000-0000-' . $slug
                    |> md5(...)
                    |> (fn($x) => substr($x, 0, 12))
                    |> (fn($x) => str_pad($x, 12, '0')),
            'youtube_url'  => "https://youtube.com/watch?v={$videoId}",
            'video_id'     => $videoId,
            'title'        => 'Test Video ' . $slug,
            'slug'         => $slug,
            'status'       => $status,
            'result_text'  => 'Transcript.',
            'summary'      => 'Summary.',
            'duration_sec' => 60,
            'completed_at' => $status === 'completed' ? now() : null,
        ];
    }
}
