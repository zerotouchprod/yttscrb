<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Seo;

use App\Application\Ports\Output\ViewTrackerInterface;
use App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeRedisViewTracker;
use Tests\TestCase;

/**
 * Feature tests for the public SEO transcript page (/v/{slug}).
 */
final class PublicTranscriptControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent live Redis dependency in these structural tests.
        Queue::fake();
        $tracker = new FakeRedisViewTracker(isRecentlyViewed: false);
        $this->app->instance(ViewTrackerInterface::class, $tracker);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function testShowsTranscriptPageForValidSlug(): void
    {
        MediaTaskModel::query()->create([
            'id'           => 'aaaaaaaa-0000-0000-0000-000000000001',
            'youtube_url'  => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'video_id'     => 'dQw4w9WgXcQ',
            'title'        => 'Rick Astley Never Gonna Give You Up',
            'slug'         => 'rick-astley-never-gonna-give-you-up',
            'status'       => 'completed',
            'result_text'  => 'We are no strangers to love.',
            'summary'      => [
                'introduction' => 'A timeless 80s pop anthem about devotion.',
                'key_points' => [],
                'conclusion' => null
            ],
            'duration_sec' => 213,
            'completed_at' => now(),
        ]);

        $response = $this->get('/v/rick-astley-never-gonna-give-you-up');

        $response->assertOk()
            ->assertSee('Rick Astley Never Gonna Give You Up')
            ->assertSee('A timeless 80s pop anthem about devotion.')
            ->assertSee('We are no strangers to love.')
            ->assertSee('AI Summary')
            ->assertSee('Full Transcript');
    }

    public function testIncludesCanonicalUrlInHead(): void
    {
        MediaTaskModel::query()->create([
            'id'           => 'aaaaaaaa-0000-0000-0000-000000000002',
            'youtube_url'  => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'video_id'     => 'dQw4w9WgXcQ',
            'title'        => 'Test Video',
            'slug'         => 'test-video',
            'status'       => 'completed',
            'result_text'  => 'Some transcript.',
            'summary'      => ['introduction' => 'A short summary.', 'key_points' => [], 'conclusion' => null],
            'duration_sec' => 60,
            'completed_at' => now(),
        ]);

        $response = $this->get('/v/test-video');

        $response->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('/v/test-video');
    }

    public function testIncludesDmcaLinkInFooter(): void
    {
        MediaTaskModel::query()->create([
            'id'           => 'aaaaaaaa-0000-0000-0000-000000000003',
            'youtube_url'  => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'video_id'     => 'dQw4w9WgXcQ',
            'title'        => 'Another Video',
            'slug'         => 'another-video',
            'status'       => 'completed',
            'result_text'  => 'Transcript.',
            'summary'      => ['introduction' => 'Summary.', 'key_points' => [], 'conclusion' => null],
            'duration_sec' => 120,
            'completed_at' => now(),
        ]);

        $this->get('/v/another-video')
            ->assertOk()
            ->assertSee('/dmca');
    }

    // -----------------------------------------------------------------------
    // 404 cases
    // -----------------------------------------------------------------------

    public function testReturns404ForUnknownSlug(): void
    {
        $this->get('/v/this-slug-does-not-exist')
            ->assertNotFound();
    }

    public function testReturns404ForDmcaRemovedPage(): void
    {
        MediaTaskModel::query()->create([
            'id'              => 'aaaaaaaa-0000-0000-0000-000000000004',
            'youtube_url'     => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'video_id'        => 'dQw4w9WgXcQ',
            'title'           => 'Removed Video',
            'slug'            => 'removed-video',
            'status'          => 'completed',
            'result_text'     => 'Transcript.',
            'summary'         => 'Summary.',
            'duration_sec'    => 60,
            'completed_at'    => now(),
            'dmca_removed_at' => now(),
        ]);

        $this->get('/v/removed-video')
            ->assertNotFound();
    }

    public function testReturns404ForNonCompletedTaskSlug(): void
    {
        // Even if a slug somehow exists for a pending task, it should 404.
        MediaTaskModel::query()->create([
            'id'          => 'aaaaaaaa-0000-0000-0000-000000000005',
            'youtube_url' => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'video_id'    => 'dQw4w9WgXcQ',
            'title'       => 'Pending Video',
            'slug'        => 'pending-video',
            'status'      => 'pending',
        ]);

        $this->get('/v/pending-video')
            ->assertNotFound();
    }
}
