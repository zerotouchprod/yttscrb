<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\ViewTrackerInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Serves GET /trending — the weekly top-20 most-read transcript page.
 *
 * Source-of-truth for ranking: Redis sorted set "trending:weekly".
 * Fallback (cold start / post-reset): DB ORDER BY views_count DESC.
 */
final class TrendingController extends Controller
{
    private const TOP_LIMIT = 20;

    public function __construct(
        private readonly MediaTaskRepositoryInterface $repository,
        private readonly ViewTrackerInterface $viewTracker,
    ) {
    }

    public function __invoke(): View
    {
        $tasks = $this->resolveTrending();

        return view('trending', [
            'tasks'     => $tasks,
            'canonical' => url('/trending'),
        ]);
    }

    /**
     * @return \App\Domain\Entities\MediaTask[]
     */
    private function resolveTrending(): array
    {
        if ($this->viewTracker->hasTrendingData()) {
            $ids = $this->viewTracker->getTopTaskIds(self::TOP_LIMIT);

            if ($ids !== []) {
                return $this->repository->findByIds($ids);
            }
        }

        // Fallback: order by lifetime views_count from DB.
        return $this->repository->findTrending(self::TOP_LIMIT);
    }
}
