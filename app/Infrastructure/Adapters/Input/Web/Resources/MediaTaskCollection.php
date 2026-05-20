<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\Entities\MediaTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

final class MediaTaskCollection extends ResourceCollection
{
    /** @var array<string, mixed> */
    private array $extraMeta;

    private ?string $searchQuery;

    /**
     * @param LengthAwarePaginator<int, MediaTask> $paginator
     * @param array<string, mixed> $extraMeta
     */
    public function __construct(
        private readonly LengthAwarePaginator $paginator,
        array $extraMeta = [],
        ?string $searchQuery = null,
    ) {
        $this->extraMeta = $extraMeta;
        $this->searchQuery = $searchQuery;
        parent::__construct($paginator->getCollection());
    }

    /**
     * @return array{data: mixed, meta: array<string, mixed>, _links: array<string, string|null>}
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => MediaTaskListItemResource::collection($this->collection)->toArray($request),
            'meta' => [
                'current_page' => $this->paginator->currentPage(),
                'last_page'    => $this->paginator->lastPage(),
                'per_page'     => $this->paginator->perPage(),
                'total'        => $this->paginator->total(),
                ...$this->extraMeta,
            ],
            '_links' => [
                'first' => $this->buildPageUrl(1),
                'prev'  => $this->paginator->currentPage() > 1
                    ? $this->buildPageUrl($this->paginator->currentPage() - 1)
                    : null,
                'next'  => $this->paginator->currentPage() < $this->paginator->lastPage()
                    ? $this->buildPageUrl($this->paginator->currentPage() + 1)
                    : null,
                'last'  => $this->buildPageUrl($this->paginator->lastPage()),
            ],
        ];
    }

    private function buildPageUrl(int $page): string
    {
        if ($this->searchQuery !== null) {
            return '/api/search?q=' . urlencode($this->searchQuery)
                . '&per_page=' . $this->paginator->perPage()
                . '&page=' . $page;
        }

        return '/api/history?page=' . $page
            . '&per_page=' . $this->paginator->perPage();
    }
}
