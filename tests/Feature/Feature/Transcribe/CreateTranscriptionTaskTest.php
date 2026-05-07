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
            'summary' => 'Existing summary',
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
}
