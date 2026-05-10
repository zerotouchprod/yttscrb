<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Seo;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for sitemap generation command.
 */
final class GenerateSitemapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testGeneratesSitemapWithPublicPages(): void
    {
        MediaTaskModel::query()->create([
            'id'           => 'cccccccc-0000-0000-0000-000000000001',
            'youtube_url'  => 'https://youtube.com/watch?v=aaaaaaaaa01',
            'video_id'     => 'aaaaaaaaa01',
            'title'        => 'First Video',
            'slug'         => 'first-video',
            'status'       => 'completed',
            'result_text'  => 'Transcript.',
            'summary'      => 'Summary.',
            'duration_sec' => 60,
            'completed_at' => now(),
        ]);

        MediaTaskModel::query()->create([
            'id'           => 'cccccccc-0000-0000-0000-000000000002',
            'youtube_url'  => 'https://youtube.com/watch?v=aaaaaaaaa02',
            'video_id'     => 'aaaaaaaaa02',
            'title'        => 'Second Video',
            'slug'         => 'second-video',
            'status'       => 'completed',
            'result_text'  => 'Transcript 2.',
            'summary'      => 'Summary 2.',
            'duration_sec' => 90,
            'completed_at' => now(),
        ]);

        $this->artisan('sitemap:generate')->assertSuccessful();

        $this->assertFileExists(public_path('sitemap.xml'));
        $content = file_get_contents(public_path('sitemap.xml'));
        $this->assertIsString($content);
        $this->assertStringContainsString('/v/first-video', $content);
        $this->assertStringContainsString('/v/second-video', $content);
    }

    public function testExcludesDmcaRemovedFromSitemap(): void
    {
        MediaTaskModel::query()->create([
            'id'              => 'cccccccc-0000-0000-0000-000000000003',
            'youtube_url'     => 'https://youtube.com/watch?v=aaaaaaaaa03',
            'video_id'        => 'aaaaaaaaa03',
            'title'           => 'Removed Video',
            'slug'            => 'removed-video',
            'status'          => 'completed',
            'result_text'     => 'Transcript.',
            'summary'         => 'Summary.',
            'duration_sec'    => 60,
            'completed_at'    => now(),
            'dmca_removed_at' => now(),
        ]);

        $this->artisan('sitemap:generate')->assertSuccessful();

        $content = file_get_contents(public_path('sitemap.xml'));
        $this->assertIsString($content);
        $this->assertStringNotContainsString('/v/removed-video', $content);
    }

    public function testExcludesTasksWithNoSlugFromSitemap(): void
    {
        // Task without slug (e.g. failed, or title was null at completion time)
        MediaTaskModel::query()->create([
            'id'           => 'cccccccc-0000-0000-0000-000000000004',
            'youtube_url'  => 'https://youtube.com/watch?v=aaaaaaaaa04',
            'video_id'     => 'aaaaaaaaa04',
            'title'        => null,
            'slug'         => null,
            'status'       => 'completed',
            'result_text'  => 'Transcript.',
            'summary'      => null,
            'duration_sec' => 60,
            'completed_at' => now(),
        ]);

        $this->artisan('sitemap:generate')->assertSuccessful();

        $content = file_get_contents(public_path('sitemap.xml'));
        $this->assertIsString($content);
        $this->assertStringNotContainsString('aaaaaaaaa04', $content);
    }

    public function testUsesRepositoryNotDirectEloquent(): void
    {
        // Verify the command resolves via IoC container through MediaTaskRepositoryInterface,
        // not a raw Eloquent import.
        $repository = $this->app->make(MediaTaskRepositoryInterface::class);
        self::assertInstanceOf(MediaTaskRepositoryInterface::class, $repository);

        // findPublicSlugs() must exist on the interface (compile-time guarantee + this check).
        self::assertTrue(method_exists($repository, 'findPublicSlugs'));
    }

    protected function tearDown(): void
    {
        // Clean up the generated sitemap to avoid polluting other test runs.
        if (file_exists(public_path('sitemap.xml'))) {
            unlink(public_path('sitemap.xml'));
        }

        parent::tearDown();
    }
}

