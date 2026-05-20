<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Adapters\Input\Web\OpenApi\OpenApiConfig;
use Illuminate\Console\Command;
use OpenApi\Generator;

final class GenerateOpenApiCommand extends Command
{
    protected $signature = 'openapi:generate';
    protected $description = 'Generate openapi.json from #[OA\*] attributes';

    public function handle(): int
    {
        /** @var array<string, mixed> $raw */
        $raw = config('openapi');
        $config = OpenApiConfig::fromArray($raw);
        $openapi = new Generator()->generate($config->scanPaths);

        if ($openapi === null) {
            $this->error('Failed to generate OpenAPI spec. Check scan paths and annotations.');

            return self::FAILURE;
        }

        file_put_contents($config->outputPath, $openapi->toJson());

        $this->info("OpenAPI spec written to {$config->outputPath}");

        return self::SUCCESS;
    }
}
