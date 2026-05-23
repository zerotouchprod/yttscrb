<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class ResourceItem
{
    public string $type;
    public string $name;
    public ?string $url;

    /**
     * @param string $type One of: book, tool, service, person, link
     */
    public function __construct(
        string $type,
        string $name,
        ?string $url,
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->url = $url !== null ? $this->normalizeUrl($url) : null;
    }

    /**
     * Ensure the URL has a protocol so it renders as an absolute link,
     * not a relative path on tubesum.app.
     */
    private function normalizeUrl(string $url): string
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return 'https://' . $url;
        }

        return $url;
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
