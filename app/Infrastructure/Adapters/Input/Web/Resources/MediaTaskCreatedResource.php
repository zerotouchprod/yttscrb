<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\Entities\MediaTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal resource for POST /api/transcribe — HTTP 202 (new pending task).
 *
 * @property-read MediaTask $resource
 */
final class MediaTaskCreatedResource extends JsonResource
{
    /**
     * @return array{task_id: string, status: string, youtube_url: string,
     *     created_at: string, _links: array{status: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'task_id'     => $this->resource->id(),
            'status'      => $this->resource->status()->value,
            'youtube_url' => $this->resource->youtubeUrl()->value(),
            'created_at'  => $this->resource->createdAt()->format('c'),
            '_links'      => [
                'status' => "/api/transcribe/{$this->resource->id()}",
            ],
        ];
    }
}
