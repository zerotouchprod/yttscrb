<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MediaTaskCreated',
    required: ['task_id', 'status', 'youtube_url', 'created_at', '_links'],
    properties: [
        new OA\Property(property: 'task_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'youtube_url', type: 'string', format: 'uri'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(
            property: '_links',
            required: ['status'],
            properties: [
                new OA\Property(
                    property: 'status',
                    type: 'string',
                    format: 'uri',
                    example: '/api/transcribe/550e8400-e29b-41d4-a716-446655440000',
                ),
            ],
            type: 'object',
        ),
    ],
)]
final class MediaTaskCreatedSchema
{
}
