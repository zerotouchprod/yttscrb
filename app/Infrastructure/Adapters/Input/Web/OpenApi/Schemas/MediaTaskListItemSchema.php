<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MediaTaskListItem',
    required: ['task_id', 'youtube_url', 'video_id', 'title', 'status', 'created_at', '_links'],
    properties: [
        new OA\Property(property: 'task_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'youtube_url', type: 'string', format: 'uri'),
        new OA\Property(property: 'video_id', type: 'string'),
        new OA\Property(property: 'title', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'completed', 'failed']),
        new OA\Property(property: 'duration_sec', type: 'integer', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(
            property: '_links',
            properties: [
                new OA\Property(property: 'public_page', type: 'string', format: 'uri', nullable: true),
            ],
            type: 'object',
        ),
    ],
)]
final class MediaTaskListItemSchema
{
}
