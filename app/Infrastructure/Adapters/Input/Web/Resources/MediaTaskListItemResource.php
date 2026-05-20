<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\Entities\MediaTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for list endpoints (history, search).
 * Does NOT include transcript or summary content.
 * Null fields are filtered out; falsy values (e.g. duration_sec = 0) are preserved.
 *
 * @property-read MediaTask $resource
 */
final class MediaTaskListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'task_id'      => $this->resource->id(),
            'youtube_url'  => $this->resource->youtubeUrl()->value(),
            'video_id'     => $this->resource->youtubeUrl()->videoId()->value(),
            'title'        => $this->resource->title(),
            'status'       => $this->resource->status()->value,
            'duration_sec' => $this->resource->durationSec(),
            'created_at'   => $this->resource->createdAt()->format('c'),
            'completed_at' => $this->resource->completedAt()?->format('c'),
            '_links'       => [
                'public_page' => ($this->resource->slug() !== null && ! $this->resource->isDmcaRemoved())
                    ? '/v/' . $this->resource->slug()
                    : null,
            ],
        ];

        // Filter only null, preserve falsy values (e.g. duration_sec = 0).
        return array_filter($data, static fn (mixed $v): bool => $v !== null);
    }
}
