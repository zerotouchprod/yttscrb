<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Seo;

use App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Admin DMCA removal endpoint.
 * POST /api/admin/tasks/{id}/dmca-remove
 */
final class AdminDmcaControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $validToken = 'test-admin-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.admin_token' => $this->validToken]);
    }

    // -----------------------------------------------------------------------
    // Auth checks
    // -----------------------------------------------------------------------

    public function testReturns401WithNoToken(): void
    {
        $id = $this->createTask();

        $this->postJson("/api/admin/tasks/{$id}/dmca-remove")
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function testReturns401WithWrongToken(): void
    {
        $id = $this->createTask();

        $this->postJson(
            "/api/admin/tasks/{$id}/dmca-remove",
            [],
            ['Authorization' => 'Bearer wrong-token'],
        )
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    // -----------------------------------------------------------------------
    // 404
    // -----------------------------------------------------------------------

    public function testReturns404ForNonExistentTask(): void
    {
        $this->postJson(
            '/api/admin/tasks/00000000-0000-0000-0000-000000000000/dmca-remove',
            [],
            ['Authorization' => "Bearer {$this->validToken}"],
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function testRemovesTaskSuccessfully(): void
    {
        $id = $this->createTask();

        $this->postJson(
            "/api/admin/tasks/{$id}/dmca-remove",
            [],
            ['Authorization' => "Bearer {$this->validToken}"],
        )
            ->assertOk()
            ->assertJsonPath('message', 'Task removed from public index.');

        /** @var MediaTaskModel $model */
        $model = MediaTaskModel::query()->find($id);
        self::assertNotNull($model->dmca_removed_at, 'dmca_removed_at should be set after removal');
    }

    public function testRemovedTaskDoesNotAppearOnPublicPage(): void
    {
        $id = $this->createTask();

        // Verify it's visible before removal
        $this->get('/v/test-dmca-video')->assertOk();

        // Remove it
        $this->postJson(
            "/api/admin/tasks/{$id}/dmca-remove",
            [],
            ['Authorization' => "Bearer {$this->validToken}"],
        )->assertOk();

        // Public page should now 404
        $this->get('/v/test-dmca-video')->assertNotFound();
    }

    public function testRemovedTaskIsExcludedFromDeduplication(): void
    {
        $id = $this->createTask();

        // Remove it via DMCA
        $this->postJson(
            "/api/admin/tasks/{$id}/dmca-remove",
            [],
            ['Authorization' => "Bearer {$this->validToken}"],
        )->assertOk();

        // Submitting the same video_id should NOT return the removed task as a dedup hit.
        // We bind a no-op dispatcher so the new task starts normally.
        $dispatcher = new class () implements \App\Application\Ports\Output\WorkflowDispatcherInterface {
            public function dispatch(\App\Domain\Entities\MediaTask $task): void
            {
            }
        };
        $this->app->instance(\App\Application\Ports\Output\WorkflowDispatcherInterface::class, $dispatcher);

        $response = $this->postJson('/api/transcribe', [
            'youtube_url' => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        // Should accept as a new task, not return the removed one.
        $response->assertAccepted()
            ->assertJsonPath('status', 'pending');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createTask(): string
    {
        $id = 'bbbbbbbb-0000-0000-0000-000000000001';

        MediaTaskModel::query()->create([
            'id'           => $id,
            'youtube_url'  => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'video_id'     => 'dQw4w9WgXcQ',
            'title'        => 'Test DMCA Video',
            'slug'         => 'test-dmca-video',
            'status'       => 'completed',
            'result_text'  => 'Some transcript.',
            'summary'      => 'A summary.',
            'duration_sec' => 90,
            'completed_at' => now(),
        ]);

        return $id;
    }
}

