<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi;

use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\ErrorSchema;
use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\LatestMediaTaskEmptySchema;
use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\LatestMediaTaskSchema;
use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\MediaTaskCreatedSchema;
use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\MediaTaskListItemSchema;
use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\MediaTaskSchema;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'yttscrb API',
    version: '1.0.0',
    description: 'YouTube Transcriber & Summarizer — Public API.',
)]
#[OA\Tag(name: 'Transcription', description: 'Create and manage transcription tasks')]
#[OA\Tag(name: 'History', description: 'Browse and search completed transcriptions')]
/** @codeCoverageIgnore OpenAPI annotation container — never executed at runtime. */
final class TranscribeVideoApi
{
    // ── POST /api/transcribe ────────────────────────────────────────────

    #[OA\Post(
        path: '/api/transcribe',
        summary: 'Create a transcription task',
        description: 'Submits a YouTube URL for transcription and AI summarization.',
        tags: ['Transcription'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['youtube_url'],
                properties: [
                    new OA\Property(
                        property: 'youtube_url',
                        type: 'string',
                        format: 'uri',
                        example: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Task created (new video)',
                content: new OA\JsonContent(ref: '#/components/schemas/MediaTaskCreated'),
            ),
            new OA\Response(
                response: 200,
                description: 'Task already completed (deduplication)',
                content: new OA\JsonContent(ref: '#/components/schemas/MediaTask'),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid YouTube URL',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 422,
                description: 'Video too long (>30 min)',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 429,
                description: 'Daily quota exceeded (10/day)',
                headers: [
                    new OA\Header(
                        header: 'Retry-After',
                        description: 'Seconds until quota resets',
                        schema: new OA\Schema(type: 'integer'),
                    ),
                ],
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function create(): void
    {
    }

    // ── GET /api/transcribe/{id} ────────────────────────────────────────

    #[OA\Get(
        path: '/api/transcribe/{id}',
        summary: 'Get task status',
        tags: ['Transcription'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Task status (shape varies by status)',
                content: new OA\JsonContent(ref: '#/components/schemas/MediaTask'),
            ),
            new OA\Response(
                response: 404,
                description: 'Task not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function status(): void
    {
    }

    // ── GET /api/transcribe/{id}/download ───────────────────────────────

    #[OA\Get(
        path: '/api/transcribe/{id}/download',
        summary: 'Download transcript as TXT',
        tags: ['Transcription'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Plain text transcript',
                content: new OA\MediaType(
                    mediaType: 'text/plain',
                    schema: new OA\Schema(type: 'string'),
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Task not found or not completed',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function download(): void
    {
    }

    // ── GET /api/history ────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/history',
        summary: 'List transcription history',
        tags: ['History'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                schema: new OA\Schema(type: 'integer', default: 15, maximum: 50),
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['pending', 'processing', 'completed', 'failed'],
                ),
            ),
            new OA\Parameter(name: 'public', in: 'query', schema: new OA\Schema(type: 'string', enum: ['1'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated history',
                content: new OA\JsonContent(
                    required: ['data', 'meta', '_links'],
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/MediaTaskListItem'),
                        ),
                        new OA\Property(
                            property: 'meta',
                            required: ['current_page', 'last_page', 'per_page', 'total'],
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'last_page', type: 'integer'),
                                new OA\Property(property: 'per_page', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(
                            property: '_links',
                            properties: [
                                new OA\Property(property: 'first', type: 'string', format: 'uri'),
                                new OA\Property(property: 'prev', type: 'string', format: 'uri', nullable: true),
                                new OA\Property(property: 'next', type: 'string', format: 'uri', nullable: true),
                                new OA\Property(property: 'last', type: 'string', format: 'uri'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function history(): void
    {
    }

    // ── GET /api/history/latest ─────────────────────────────────────────

    #[OA\Get(
        path: '/api/history/latest',
        summary: 'Get latest completed transcription',
        tags: ['History'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Latest completed task or null',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/LatestMediaTask'),
                        new OA\Schema(ref: '#/components/schemas/LatestMediaTaskEmpty'),
                    ],
                ),
            ),
        ],
    )]
    public function latest(): void
    {
    }

    // ── GET /api/search ─────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/search',
        summary: 'Search transcriptions by title',
        tags: ['History'],
        parameters: [
            new OA\Parameter(
                name: 'q',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 100),
            ),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                schema: new OA\Schema(type: 'integer', default: 15, maximum: 50),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Search results',
                content: new OA\JsonContent(
                    required: ['data', 'meta', '_links'],
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/MediaTaskListItem'),
                        ),
                        new OA\Property(
                            property: 'meta',
                            required: ['current_page', 'last_page', 'per_page', 'total', 'query'],
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'last_page', type: 'integer'),
                                new OA\Property(property: 'per_page', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                                new OA\Property(property: 'query', type: 'string'),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(
                            property: '_links',
                            properties: [
                                new OA\Property(property: 'first', type: 'string', format: 'uri'),
                                new OA\Property(property: 'prev', type: 'string', format: 'uri', nullable: true),
                                new OA\Property(property: 'next', type: 'string', format: 'uri', nullable: true),
                                new OA\Property(property: 'last', type: 'string', format: 'uri'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid query (empty, too short, too long, wildcard-only)',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function search(): void
    {
    }
}
