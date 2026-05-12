<?php

declare(strict_types=1);

namespace Tests\Feature\Unit\Infrastructure\Adapters\Input\Web;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\SubtitleProviderInterface;
use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\VideoId;
use DateTimeImmutable;
use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

final class TranscribeVideoControllerTest extends TestCase
{
    private MediaTaskRepositoryInterface $repository;
    private SubtitleProviderInterface $subtitleProvider;
    private WorkflowDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = \Mockery::mock(MediaTaskRepositoryInterface::class);
        $this->subtitleProvider = \Mockery::mock(SubtitleProviderInterface::class);
        $this->dispatcher = \Mockery::mock(WorkflowDispatcherInterface::class);

        Container::getInstance()->instance(MediaTaskRepositoryInterface::class, $this->repository);
        Container::getInstance()->instance(SubtitleProviderInterface::class, $this->subtitleProvider);
        Container::getInstance()->instance(WorkflowDispatcherInterface::class, $this->dispatcher);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    // ─── 429: Daily Limit Exceeded ──────────────────────────────────────────

    public function testCreateReturns429WhenDailyLimitExceeded(): void
    {
        $this->repository
            ->shouldReceive('countCompletedSince')
            ->once()
            ->andReturn(10);
        // yt-dlp should NOT be called when the daily limit is already exceeded.
        $this->subtitleProvider->shouldNotReceive('extractDuration');
        $this->dispatcher->shouldNotReceive('dispatch');

        $response = $this->postJson('/api/transcribe', [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'details' => ['limit' => 10, 'used' => 10],
            ],
        ]);
        $response->assertHeader('Retry-After');
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
    }

    public function testCreateReturns429WhenAboveDailyLimit(): void
    {
        $this->repository
            ->shouldReceive('countCompletedSince')
            ->once()
            ->andReturn(15); // Well above limit
        $this->subtitleProvider->shouldNotReceive('extractDuration');
        $this->dispatcher->shouldNotReceive('dispatch');

        $response = $this->postJson('/api/transcribe', [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'details' => ['limit' => 10, 'used' => 15],
            ],
        ]);
    }

    // ─── 422: Video Too Long ────────────────────────────────────────────────

    public function testCreateReturns422WhenVideoExceedsMaxDuration(): void
    {
        $this->repository
            ->shouldReceive('countCompletedSince')
            ->once()
            ->andReturn(3);
        $this->subtitleProvider
            ->shouldReceive('extractDuration')
            ->with('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->once()
            ->andReturn(3600); // 1 hour > 30 min
        $this->dispatcher->shouldNotReceive('dispatch');

        $response = $this->postJson('/api/transcribe', [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => [
                'code' => 'VIDEO_TOO_LONG',
                'details' => ['max_duration_sec' => 1800, 'video_duration_sec' => 3600],
            ],
        ]);
    }

    // ─── Edge: Exactly 30 min, null duration ────────────────────────────────

    public function testCreateAllowsVideoExactly30Minutes(): void
    {
        $this->repository
            ->shouldReceive('countCompletedSince')
            ->once()
            ->andReturn(3);
        $this->subtitleProvider
            ->shouldReceive('extractDuration')
            ->once()
            ->andReturn(1800); // exactly 30 minutes — permitted

        $this->expectTaskSavedAndDispatched();

        $response = $this->postJson('/api/transcribe', [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(202);
    }

    public function testCreateAllowsVideoWhenDurationIsNull(): void
    {
        // yt-dlp may return null if the video is unavailable — we should
        // allow the request to proceed (the workflow will handle the failure).
        $this->repository
            ->shouldReceive('countCompletedSince')
            ->once()
            ->andReturn(3);
        $this->subtitleProvider
            ->shouldReceive('extractDuration')
            ->once()
            ->andReturn(null);

        $this->expectTaskSavedAndDispatched();

        $response = $this->postJson('/api/transcribe', [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(202);
    }

    // ─── 202: Valid URL, Within Limits ──────────────────────────────────────

    public function testCreateReturns202ForValidUrlWithinLimits(): void
    {
        $this->repository
            ->shouldReceive('countCompletedSince')
            ->once()
            ->andReturn(3);
        $this->subtitleProvider
            ->shouldReceive('extractDuration')
            ->once()
            ->andReturn(120); // 2 minutes — well under limit

        $this->expectTaskSavedAndDispatched();

        $response = $this->postJson('/api/transcribe', [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'task_id',
            'status',
            'youtube_url',
            'created_at',
            '_links' => ['status'],
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function expectTaskSavedAndDispatched(): void
    {
        $this->repository->shouldReceive('findCompletedByVideoId')->once()->andReturn(null);
        $this->repository->shouldReceive('save')->once();
        $this->dispatcher->shouldReceive('dispatch')->once();
    }
}
