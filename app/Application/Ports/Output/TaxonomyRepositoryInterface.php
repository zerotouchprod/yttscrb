<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Domain\Entities\Taxonomy;
use App\Domain\ValueObjects\TaxonomyType;

interface TaxonomyRepositoryInterface
{
    /**
     * Find existing taxonomy by type+slug or create it.
     * Increments video_count on every task attachment.
     */
    public function findOrCreate(TaxonomyType $type, string $name): Taxonomy;

    public function findByTypeAndSlug(TaxonomyType $type, string $slug): ?Taxonomy;

    /** @return Taxonomy[] */
    public function paginateByType(TaxonomyType $type, int $page, int $perPage): array;

    /** @return int Total count of taxonomies of given type */
    public function countByType(TaxonomyType $type): int;

    /**
     * Attach a taxonomy to a media task. Idempotent — safe to call multiple times.
     * Increments taxonomy.video_count by 1 on first attach (not on duplicate).
     */
    public function attachToTask(string $taskId, Taxonomy $taxonomy): void;

    /**
     * Paginate media tasks for a given taxonomy.
     *
     * @return array{data: array<int, mixed>, total: int}
     */
    public function paginateTasksByTaxonomy(Taxonomy $taxonomy, int $page, int $perPage): array;
}
