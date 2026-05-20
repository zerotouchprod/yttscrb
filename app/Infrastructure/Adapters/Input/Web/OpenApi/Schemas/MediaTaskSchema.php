<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MediaTask',
    description: 'Full task resource (status/latest/dedup-200 endpoints). Fields vary by status.',
    required: ['task_id', 'status', 'youtube_url', 'video_id', 'title', 'created_at', '_links'],
    properties: [
        new OA\Property(property: 'task_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'completed', 'failed']),
        new OA\Property(property: 'youtube_url', type: 'string', format: 'uri'),
        new OA\Property(property: 'video_id', type: 'string', example: 'dQw4w9WgXcQ'),
        new OA\Property(property: 'title', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'duration_sec', type: 'integer', nullable: true),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'error_message', type: 'string', nullable: true),
        new OA\Property(property: 'failed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'estimated_completion_sec', type: 'integer', example: 90),
        new OA\Property(
            property: 'result',
            properties: [
                new OA\Property(property: 'transcript', type: 'string'),
                new OA\Property(property: 'summary', ref: '#/components/schemas/Summary', nullable: true),
                new OA\Property(property: 'word_count', type: 'integer'),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(
            property: '_links',
            properties: [
                new OA\Property(property: 'self', type: 'string', format: 'uri'),
                new OA\Property(property: 'download_txt', type: 'string', format: 'uri'),
                new OA\Property(property: 'public_page', type: 'string', format: 'uri', nullable: true),
            ],
            type: 'object',
        ),
    ],
)]
final class MediaTaskSchema
{
}
