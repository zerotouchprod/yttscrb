<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LatestMediaTask',
    required: ['task_id', 'youtube_url', 'title', 'status', 'duration_sec', 'result',
        'created_at', 'completed_at', '_links'],
    properties: [
        new OA\Property(property: 'task_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'youtube_url', type: 'string', format: 'uri'),
        new OA\Property(property: 'title', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['completed']),
        new OA\Property(property: 'duration_sec', type: 'integer'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(
            property: 'result',
            required: ['transcript', 'summary', 'word_count'],
            properties: [
                new OA\Property(property: 'transcript', type: 'string'),
                new OA\Property(property: 'summary', ref: '#/components/schemas/Summary', nullable: true),
                new OA\Property(property: 'word_count', type: 'integer'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: '_links',
            required: ['download_txt'],
            properties: [
                new OA\Property(property: 'download_txt', type: 'string', format: 'uri'),
            ],
            type: 'object',
        ),
    ],
)]
final class LatestMediaTaskSchema
{
}
