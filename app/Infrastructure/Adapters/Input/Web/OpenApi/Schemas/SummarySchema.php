<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Summary',
    required: ['introduction', 'key_points', 'conclusion', 'resources', 'tutorial_steps', 'chapters', 'flashcards', 'highlights', 'blog_post', 'linkedin_post', 'topics'],
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
        new OA\Property(
            property: 'chapters',
            type: 'array',
            items: new OA\Items(
                required: ['title', 'start_timecode', 'end_timecode'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Introduction'),
                    new OA\Property(property: 'start_timecode', type: 'string', example: '00:00:00'),
                    new OA\Property(property: 'end_timecode', type: 'string', example: '00:05:30'),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(
            property: 'flashcards',
            type: 'array',
            items: new OA\Items(
                required: ['question', 'answer', 'source_timecode', 'difficulty'],
                properties: [
                    new OA\Property(property: 'question', type: 'string', example: 'What is the Dependency Rule?'),
                    new OA\Property(property: 'answer', type: 'string', example: 'Source code dependencies can only point inwards.'),
                    new OA\Property(property: 'source_timecode', type: 'string', example: '00:05:30'),
                    new OA\Property(property: 'difficulty', type: 'string', example: 'medium'),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(
            property: 'highlights',
            type: 'array',
            items: new OA\Items(
                required: ['timecode', 'title', 'why_notable', 'category'],
                properties: [
                    new OA\Property(property: 'timecode', type: 'string', example: '00:12:34'),
                    new OA\Property(property: 'title', type: 'string', example: 'The big reveal'),
                    new OA\Property(property: 'why_notable', type: 'string', example: 'Speaker unexpectedly announces open-source.'),
                    new OA\Property(property: 'category', type: 'string', example: 'surprise'),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(
            property: 'content_meta',
            required: ['complexity', 'reading_time_minutes', 'jargon_density', 'target_audience'],
            properties: [
                new OA\Property(property: 'complexity', type: 'string', example: 'intermediate'),
                new OA\Property(property: 'reading_time_minutes', type: 'integer', example: 12),
                new OA\Property(property: 'jargon_density', type: 'string', example: 'moderate'),
                new OA\Property(property: 'target_audience', type: 'string', example: 'Software developers with basic Kubernetes experience'),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(
            property: 'blog_post',
            required: ['title', 'sections'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'How to Build Scalable APIs with Laravel'),
                new OA\Property(
                    property: 'sections',
                    type: 'array',
                    items: new OA\Items(
                        required: ['heading', 'body'],
                        properties: [
                            new OA\Property(property: 'heading', type: 'string', example: 'Setting Up Your Environment'),
                            new OA\Property(
                                property: 'body',
                                type: 'string',
                                example: 'Ensure you have PHP and Composer installed.',
                            ),
                        ],
                        type: 'object',
                    ),
                ),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(
            property: 'linkedin_post',
            required: ['hook', 'body', 'call_to_action'],
            properties: [
                new OA\Property(property: 'hook', type: 'string', example: 'Most teams ship slow. Here is why.'),
                new OA\Property(property: 'body', type: 'string', example: 'The real bottleneck is invisible hand-offs.\n\nSecond paragraph.'),
                new OA\Property(property: 'call_to_action', type: 'string', example: 'Full AI summary → [URL]'),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(
            property: 'topics',
            type: 'array',
            items: new OA\Items(
                type: 'string',
                example: 'machine learning',
            ),
        ),
    ],
)]
final class SummarySchema
{
}
