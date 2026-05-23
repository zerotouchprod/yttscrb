<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class ResourceItem
{
    /**
     * @param string $type One of: book, tool, service, person, link
     */
    public function __construct(
        public string $type,
        public string $name,
        public ?string $url,
    ) {
    }

    /**
     * @return array{type: string, name: string, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'url'  => $this->url,
        ];
    }

    /**
     * @param array{type: string, name: string, url?: string|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            name: $data['name'],
            url: $data['url'] ?? null,
        );
    }
}
