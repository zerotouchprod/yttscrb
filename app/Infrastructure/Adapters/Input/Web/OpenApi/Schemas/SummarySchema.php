<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Summary',
    required: ['introduction', 'key_points', 'conclusion', 'resources', 'tutorial_steps'],
    properties: [
        new OA\Property(property: 'introduction', type: 'string'),
        new OA\Property(
            property: 'key_points',
            type: 'array',
            items: new OA\Items(
                required: ['timecode', 'title', 'details'],
                properties: [
                    new OA\Property(property: 'timecode', type: 'string', example: '00:02:30'),
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'details', type: 'string'),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(property: 'conclusion', type: 'string', nullable: true),
        new OA\Property(
            property: 'resources',
            type: 'array',
            items: new OA\Items(
                required: ['type', 'name'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', example: 'book'),
                    new OA\Property(property: 'name', type: 'string', example: 'Clean Code'),
                    new OA\Property(property: 'url', type: 'string', nullable: true, example: 'https://example.com'),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(
            property: 'clickbait_verdict',
            required: ['score', 'comment'],
            properties: [
                new OA\Property(property: 'score', type: 'integer', example: 85),
                new OA\Property(property: 'comment', type: 'string', example: 'Title matches content well.'),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(
            property: 'tutorial_steps',
            type: 'array',
            items: new OA\Items(
                required: ['step', 'time', 'action'],
                properties: [
                    new OA\Property(property: 'step', type: 'integer', example: 1),
                    new OA\Property(property: 'time', type: 'string', example: '03:45'),
                    new OA\Property(property: 'action', type: 'string', example: 'composer require laravel/horizon'),
                ],
                type: 'object',
            ),
        ),
    ],
)]
final class SummarySchema
{
}
