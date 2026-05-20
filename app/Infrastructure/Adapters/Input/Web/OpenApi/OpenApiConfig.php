<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi;

final readonly class OpenApiConfig
{
    /**
     * @param array<int, array{url: string, description: string}> $servers
     * @param string[] $scanPaths
     */
    public function __construct(
        public string $title,
        public string $version,
        public string $description,
        public array $servers,
        public array $scanPaths,
        public string $outputPath,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            title: (string) ($config['title'] ?? ''),
            version: (string) ($config['version'] ?? '1.0.0'),
            description: (string) ($config['description'] ?? ''),
            /** @phpstan-ignore-next-line config() returns mixed */
            servers: (array) ($config['servers'] ?? []),
            /** @phpstan-ignore-next-line config() returns mixed */
            scanPaths: (array) ($config['scan_paths'] ?? []),
            outputPath: (string) ($config['output_path'] ?? ''),
        );
    }
}
