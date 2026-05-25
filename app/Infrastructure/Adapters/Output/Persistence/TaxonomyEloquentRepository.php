<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Persistence;

use App\Application\Ports\Output\TaxonomyRepositoryInterface;
use App\Domain\Entities\Taxonomy;
use App\Domain\ValueObjects\TaxonomyType;
use App\Models\MediaTaskTaxonomyModel;
use App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel;
use App\Models\TaxonomyModel;
use Illuminate\Support\Str;
use LogicException;

final class TaxonomyEloquentRepository implements TaxonomyRepositoryInterface
{
    public function findOrCreate(TaxonomyType $type, string $name): Taxonomy
    {
        $slug = Str::slug($name);

        $model = TaxonomyModel::query()->where('type', $type->value)
            ->where('slug', $slug)
            ->first();

        if ($model === null) {
            $model = TaxonomyModel::query()->create([
                'type'        => $type->value,
                'name'        => $name,
                'slug'        => $slug,
                'video_count' => 0,
            ]);
        }

        return $this->toEntity($model);
    }

    public function findByTypeAndSlug(TaxonomyType $type, string $slug): ?Taxonomy
    {
        $model = TaxonomyModel::query()->where('type', $type->value)
            ->where('slug', $slug)
            ->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    /** @return Taxonomy[] */
    public function paginateByType(TaxonomyType $type, int $page, int $perPage): array
    {
        $models = TaxonomyModel::query()->where('type', $type->value)
            ->orderBy('video_count', 'desc')
            ->orderBy('name')
            ->forPage($page, $perPage)
            ->get();

        /** @phpstan-ignore-next-line */
        return $models->map(fn (TaxonomyModel $m) => $this->toEntity($m))->all();
    }

    public function countByType(TaxonomyType $type): int
    {
        return TaxonomyModel::query()->where('type', $type->value)->count();
    }

    public function attachToTask(string $taskId, Taxonomy $taxonomy): void
    {
        $exists = MediaTaskTaxonomyModel::query()->where('media_task_id', $taskId)
            ->where('taxonomy_id', $taxonomy->id())
            ->exists();

        if ($exists) {
            return;
        }

        MediaTaskTaxonomyModel::query()->create([
            'media_task_id' => $taskId,
            'taxonomy_id'   => $taxonomy->id(),
        ]);

        TaxonomyModel::query()->where('id', $taxonomy->id())->increment('video_count');
    }

    /** @return array{data: array<int, mixed>, total: int} */
    public function paginateTasksByTaxonomy(Taxonomy $taxonomy, int $page, int $perPage): array
    {
        $taskIds = MediaTaskTaxonomyModel::query()
            ->where('taxonomy_id', $taxonomy->id())
            ->pluck('media_task_id');

        $query = MediaTaskModel::query()
            ->whereIn('id', $taskIds)
            ->where('status', 'completed')
            ->where(function ($q): void {
                $q->whereNull('dmca_removed_at')
                    ->orWhere('dmca_removed_at', false);
            })
            ->orderBy('completed_at', 'desc');

        $total = $query->count();
        $rows = $query->forPage($page, $perPage)->get();

        return [
            'data' => $rows->all(),
            'total' => $total,
        ];
    }

    private function toEntity(TaxonomyModel $model): Taxonomy
    {
        $type = TaxonomyType::from($model->type);

        return new Taxonomy(
            id: $model->id,
            type: $type,
            name: $model->name,
            slug: $model->slug,
            videoCount: (int) $model->video_count,
        );
    }
}
