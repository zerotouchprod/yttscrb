<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorResponse',
    required: ['error'],
    properties: [
        new OA\Property(
            property: 'error',
            required: ['code', 'message'],
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'TASK_NOT_FOUND'),
                new OA\Property(property: 'message', type: 'string', example: 'Task not found.'),
                new OA\Property(
                    property: 'details',
                    type: 'object',
                    nullable: true,
                    additionalProperties: new OA\AdditionalProperties(type: 'string'),
                ),
            ],
            type: 'object',
        ),
    ],
)]
final class ErrorSchema
{
}
