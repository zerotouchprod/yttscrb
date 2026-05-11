<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Seo;

use App\Domain\ValueObjects\SummaryResult;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    private const string ID_TASK_1     = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const string ID_TASK_DMCA  = 'aaaaaaaa-0000-0000-0000-00000000000d';
    private const string ID_NO_SLUG    = 'aaaaaaaa-0000-0000-0000-00000000000e';
    private const string ID_LINKS      = 'aaaaaaaa-0000-0000-0000-00000000000f';
    private const string ID_CASE       = 'aaaaaaaa-0000-0000-0000-000000000010';

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (\Illuminate\Database\QueryException) {
            // Extension unsupported on SQLite — skip silently
        }
    }

    public function testSuccessfulSearchReturnsMatchingTasks(): void
    {
        $task = MediaTask::create(self::ID_TASK_1, new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $task->startProcessing('wf-1');
        $task->complete('Transcript text', new SummaryResult('Summary text', []), 212);
        $task->setTitle('Rick Astley - Never Gonna Give You Up');
        $task->setSlug('rick-astley-never-gonna-give-you-up');

        $this->persistTask($task);

        $response = $this->getJson('/api/search?q=rick+astley');

        $response->assertOk();
        $response->assertJsonPath('data.0.task_id', self::ID_TASK_1);
        $response->assertJsonPath('data.0.title', 'Rick Astley - Never Gonna Give You Up');
        $response->assertJsonPath('data.0._links.public_page', '/v/rick-astley-never-gonna-give-you-up');
        $response->assertJsonPath('meta.query', 'rick astley');
    }

    public function testSearchWithEmptyResults(): void
    {
        $response = $this->getJson('/api/search?q=nonexistent');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function testQueryTooShortReturns400(): void
    {
        $response = $this->getJson('/api/search?q=a');

        $response->assertBadRequest();
        $response->assertJsonPath('error.code', 'INVALID_QUERY');
    }

    public function testWildcardOnlyQueryReturns400(): void
    {
        $response = $this->getJson('/api/search?q=%%%%%%');

        $response->assertBadRequest();
        $response->assertJsonPath('error.code', 'INVALID_QUERY');
    }

    public function testUnderscoreOnlyQueryReturns400(): void
    {
        $response = $this->getJson('/api/search?q=____');

        $response->assertBadRequest();
        $response->assertJsonPath('error.code', 'INVALID_QUERY');
    }

    public function testExcludesDmcaRemovedTasks(): void
    {
        $task = MediaTask::create(self::ID_TASK_DMCA, new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $task->startProcessing('wf-dmca');
        $task->complete('Transcript', new SummaryResult('Summary', []), 100);
        $task->setTitle('Rick Astley DMCA Removed');
        $task->setSlug('rick-astley-dmca-removed');
        $task->removeForDmca();

        $this->persistTask($task);

        $response = $this->getJson('/api/search?q=rick+astley');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function testSlugNullOmitsPublicPageLink(): void
    {
        $task = MediaTask::create(self::ID_NO_SLUG, new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $task->startProcessing('wf-no-slug');
        $task->complete('Transcript', new SummaryResult('Summary', []), 100);
        $task->setTitle('Rick Astley No Slug');

        $this->persistTask($task);

        $response = $this->getJson('/api/search?q=rick+astley');

        $response->assertOk();
        $response->assertJsonPath('data.0.task_id', self::ID_NO_SLUG);
        $response->assertJsonMissing(['data.0._links.public_page']);
    }

    public function testLinksContainCorrectQueryParameter(): void
    {
        $task = MediaTask::create(self::ID_LINKS, new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $task->startProcessing('wf-links');
        $task->complete('Transcript', new SummaryResult('Summary', []), 100);
        $task->setTitle('Rick Astley Links Test');
        $task->setSlug('rick-astley-links-test');

        $this->persistTask($task);

        $response = $this->getJson('/api/search?q=rick+astley&per_page=10');

        $response->assertOk();
        $response->assertJsonPath('_links.first', '/api/search?q=rick+astley&per_page=10&page=1');
        $response->assertJsonPath('_links.prev', null);
        $response->assertJsonPath('_links.next', null);
        $response->assertJsonPath('_links.last', '/api/search?q=rick+astley&per_page=10&page=1');
    }

    public function testMissingQueryParameterReturns400(): void
    {
        $response = $this->getJson('/api/search');

        $response->assertBadRequest();
        $response->assertJsonPath('error.code', 'INVALID_QUERY');
    }

    public function testQueryExceedsMaxLengthReturns400(): void
    {
        $longQuery = str_repeat('a', 101);

        $response = $this->getJson('/api/search?q=' . $longQuery);

        $response->assertBadRequest();
        $response->assertJsonPath('error.code', 'INVALID_QUERY');
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $task = MediaTask::create(self::ID_CASE, new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $task->startProcessing('wf-case');
        $task->complete('Transcript', new SummaryResult('Summary', []), 100);
        $task->setTitle('RICK ASTLEY UPPERCASE');
        $task->setSlug('rick-astley-uppercase');

        $this->persistTask($task);

        $response = $this->getJson('/api/search?q=rick+astley');

        $response->assertOk();
        $response->assertJsonPath('data.0.task_id', self::ID_CASE);
    }

    private function persistTask(MediaTask $task): void
    {
        $model = new MediaTaskModel();
        $model->id = $task->id();
        $model->youtube_url = $task->youtubeUrl()->value();
        $model->video_id = $task->youtubeUrl()->videoId()->value();
        $model->status = $task->status()->value;
        $model->workflow_id = $task->workflowId();
        $model->result_text = $task->resultText()?->value();
        $model->summary = $task->summary()?->toArray();
        $model->duration_sec = $task->durationSec();
        $model->title = $task->title();
        $model->slug = $task->slug();
        $model->error_message = $task->errorMessage();
        $model->completed_at = $task->completedAt() !== null ? Carbon::instance($task->completedAt()) : null;
        $model->failed_at = $task->failedAt() !== null ? Carbon::instance($task->failedAt()) : null;
        $model->dmca_removed_at = $task->dmcaRemovedAt() !== null ? Carbon::instance($task->dmcaRemovedAt()) : null;
        $model->save();
    }
}
