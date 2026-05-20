<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LatestMediaTaskEmpty',
    required: ['task_id', 'status', 'message'],
    properties: [
        new OA\Property(property: 'task_id', type: 'null'),
        new OA\Property(property: 'status', type: 'null'),
        new OA\Property(property: 'message', type: 'string', example: 'No completed transcriptions yet.'),
    ],
)]
final class LatestMediaTaskEmptySchema
{
}
