<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class BlogSection
{
    public function __construct(
        public string $heading,
        public string $body,
    ) {
    }

    /**
     * @return array{heading: string, body: string}
     */
    public function toArray(): array
    {
        return [
            'heading' => $this->heading,
            'body'    => $this->body,
        ];
    }

    /**
     * @param array{heading: string, body: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            heading: $data['heading'],
            body: $data['body'],
        );
    }
}
