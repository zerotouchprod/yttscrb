<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web;

use App\Application\Ports\Output\TaxonomyRepositoryInterface;
use App\Domain\ValueObjects\TaxonomyType;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class TaxonomyController extends Controller
{
    public function __construct(
        private readonly TaxonomyRepositoryInterface $taxonomyRepository,
    ) {
    }

    /** GET /topics — directory of all topic tags sorted by video_count DESC */
    public function topicsIndex(): View
    {
        $topics = $this->taxonomyRepository->paginateByType(TaxonomyType::Topic, page: 1, perPage: 100);

        return view('topics-index', ['topics' => $topics]);
    }

    /** GET /topic/{slug} and GET /speaker/{slug} */
    public function show(string $slug, string $type): View
    {
        $taxonomyType = TaxonomyType::from($type);
        $taxonomy = $this->taxonomyRepository->findByTypeAndSlug($taxonomyType, $slug);

        if ($taxonomy === null) {
            abort(404);
        }

        $page = max(1, (int) request()->query('page', '1'));
        $result = $this->taxonomyRepository->paginateTasksByTaxonomy($taxonomy, $page, perPage: 15);

        return view('taxonomy', [
            'taxonomy' => $taxonomy,
            'tasks'    => $result['data'],
            'total'    => $result['total'],
            'page'     => $page,
            'perPage'  => 15,
        ]);
    }
}
