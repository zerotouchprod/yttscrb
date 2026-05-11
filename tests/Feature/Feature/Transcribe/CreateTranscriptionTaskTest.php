<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Transcribe;

use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Domain\Entities\MediaTask;
use App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateTranscriptionTaskTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestCanCreateATranscriptionTaskWithoutRegistration(): void
    {
        $dispatcher = new class () implements WorkflowDispatcherInterface {
            public function dispatch(MediaTask $task): void
            {
            }
        };

        $this->app->instance(WorkflowDispatcherInterface::class, $dispatcher);

        $response = $this->postJson('/api/transcribe', [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('youtube_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        self::assertSame(1, MediaTaskModel::query()->count());
    }

    public function testReturnsValidationErrorForInvalidYoutubeUrl(): void
    {
        $dispatcher = new class () implements WorkflowDispatcherInterface {
            public function dispatch(MediaTask $task): void
            {
            }
        };

        $this->app->instance(WorkflowDispatcherInterface::class, $dispatcher);

        $response = $this->postJson('/api/transcribe', [
            'youtube_url' => 'https://example.com/video',
        ]);

        $response->assertBadRequest()
            ->assertJsonPath('error.code', 'INVALID_YOUTUBE_URL');
    }

    public function testReturnsExistingCompletedTaskForDuplicateVideo(): void
    {
        $dispatcher = new class () implements WorkflowDispatcherInterface {
            public function dispatch(MediaTask $task): void
            {
            }
        };

        $this->app->instance(WorkflowDispatcherInterface::class, $dispatcher);

        MediaTaskModel::query()->create([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'video_id' => 'dQw4w9WgXcQ',
            'status' => 'completed',
            'result_text' => 'Existing transcript',
            'summary' => ['introduction' => 'Existing summary', 'key_points' => [], 'conclusion' => null],
            'duration_sec' => 10,
            'completed_at' => now(),
        ]);

        $response = $this->postJson('/api/transcribe', [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertOk()
            ->assertJsonPath('task_id', '550e8400-e29b-41d4-a716-446655440000')
            ->assertJsonPath('status', 'completed');
    }

    public function testReturnsHistoryWithoutRegistration(): void
    {
        MediaTaskModel::query()->create([
            'id' => '11111111-e29b-41d4-a716-446655440000',
            'youtube_url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
            'video_id' => 'aaaaaaaaaaa',
            'status' => 'pending',
        ]);

        MediaTaskModel::query()->create([
            'id' => '22222222-e29b-41d4-a716-446655440000',
            'youtube_url' => 'https://www.youtube.com/watch?v=bbbbbbbbbbb',
            'video_id' => 'bbbbbbbbbbb',
            'status' => 'completed',
            'result_text' => 'Transcript',
            'summary' => ['introduction' => 'Summary', 'key_points' => [], 'conclusion' => null],
            'duration_sec' => 5,
            'completed_at' => now(),
            'slug' => 'test-slug',
        ]);

        $response = $this->getJson('/api/history');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        // Each task must include video_id for thumbnail URLs on the frontend.
        /** @var list<array{video_id: string, _links: array{public_page: string|null}}> $data */
        $data = $response->json('data');
        $this->assertNotEmpty($data[0]['video_id'] ?? null, 'Each history item must include video_id');
        $this->assertNotEmpty($data[1]['video_id'] ?? null, 'Each history item must include video_id');

        // At least one completed task should have a public_page link.
        $completedWithLink = array_filter(
            $data,
            fn (array $item): bool => ($item['_links']['public_page'] ?? null) === '/v/test-slug',
        );
        $this->assertCount(1, $completedWithLink);
    }

    public function testReturnsLatestCompletedTaskWithoutRegistration(): void
    {
        MediaTaskModel::query()->create([
            'id' => '33333333-e29b-41d4-a716-446655440000',
            'youtube_url' => 'https://www.youtube.com/watch?v=ccccccccccc',
            'video_id' => 'ccccccccccc',
            'status' => 'completed',
            'result_text' => 'Latest transcript',
            'summary' => ['introduction' => 'Latest summary', 'key_points' => [], 'conclusion' => null],
            'duration_sec' => 7,
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/history/latest');

        $response->assertOk()
            ->assertJsonPath('task_id', '33333333-e29b-41d4-a716-446655440000')
            ->assertJsonPath('status', 'completed');
    }
}
