<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\ValueObjects\SummaryResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SummaryResult $resource
 */
final class SummaryResource extends JsonResource
{
    /**
     * @return array{
     *     introduction: string,
     *     key_points: array<int, array{timecode: string, title: string, details: string}>,
     *     conclusion: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'introduction' => $this->resource->introduction(),
            'key_points' => array_map(
                fn (\App\Domain\ValueObjects\SummaryKeyPoint $kp) => [
                    'timecode' => $kp->timecode,
                    'title'    => $kp->title,
                    'details'  => $kp->details,
                ],
                $this->resource->keyPoints(),
            ),
            'conclusion' => $this->resource->conclusion(),
        ];
    }
}
