<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Input\Web\Resources\MediaTaskCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class MediaTaskCollectionTest extends TestCase
{
    /**
     * @param Collection<int, MediaTask> $items
     * @return LengthAwarePaginator<int, MediaTask>
     */
    private function makePaginator(
        int $page,
        int $last,
        int $perPg,
        int $tot,
        Collection $items,
    ): LengthAwarePaginator {
        return new LengthAwarePaginator($items, $tot, $perPg, $page);
    }

    private function makeCompletedTask(string $title, string $slug): MediaTask
    {
        $task = MediaTask::create(
            Uuid::uuid4()->toString(),
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->setTitle($title);
        $task->setSlug($slug);
        $task->startProcessing('wf-test'); $task->complete('Transcript', null, 120);
        return $task;
    }

    public function testHistoryCollectionFirstPageHasNextAndLastButNoPrev(): void
    {
        $items = collect([$this->makeCompletedTask('Test', 'test')]);
        $paginator = $this->makePaginator(1, 3, 15, 45, $items);

        $collection = new MediaTaskCollection($paginator);
        $result = $collection->toArray(new Request());

        $this->assertIsArray($result['data']);
        $this->assertCount(1, $result['data']);
        $this->assertStringContainsString(
            '/api/history?page=1',
            (string) $result['_links']['first'],
        );
        $this->assertNull($result['_links']['prev']);
        $this->assertNotNull($result['_links']['next']);
        $this->assertNotNull($result['_links']['last']);
    }

    public function testHistoryCollectionLastPageHasPrevAndFirstButNoNext(): void
    {
        $items = collect([$this->makeCompletedTask('Test', 'test')]);
        $paginator = $this->makePaginator(3, 3, 15, 45, $items);

        $collection = new MediaTaskCollection($paginator);
        $result = $collection->toArray(new Request());

        $this->assertNotNull($result['_links']['first']);
        $this->assertNotNull($result['_links']['prev']);
        $this->assertNull($result['_links']['next']);
    }

    public function testHistoryCollectionMetaContainsPaginationFields(): void
    {
        $items = collect([$this->makeCompletedTask('Test', 'test')]);
        $paginator = $this->makePaginator(2, 5, 10, 50, $items);

        $collection = new MediaTaskCollection($paginator);
        $result = $collection->toArray(new Request());

        $this->assertSame(2, $result['meta']['current_page']);
        $this->assertSame(5, $result['meta']['last_page']);
        $this->assertSame(10, $result['meta']['per_page']);
        $this->assertSame(50, $result['meta']['total']);
    }

    public function testSearchCollectionExtraMetaContainsQuery(): void
    {
        $items = collect([$this->makeCompletedTask('Test', 'test')]);
        $paginator = $this->makePaginator(1, 1, 15, 1, $items);

        $collection = new MediaTaskCollection($paginator, ['query' => 'rick astley'], 'rick astley');
        $result = $collection->toArray(new Request());

        $this->assertSame('rick astley', $result['meta']['query']);
    }

    public function testSearchCollectionLinksUseSearchUrl(): void
    {
        $items = collect([$this->makeCompletedTask('Test', 'test')]);
        $paginator = $this->makePaginator(1, 2, 10, 15, $items);

        $collection = new MediaTaskCollection($paginator, ['query' => 'test'], 'test');
        $result = $collection->toArray(new Request());

        $this->assertStringContainsString(
            '/api/search?q=test',
            (string) $result['_links']['first'],
        );
        $this->assertStringContainsString(
            '/api/search?q=test',
            (string) $result['_links']['next'],
        );
    }
}
