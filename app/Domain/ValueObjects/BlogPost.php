<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class BlogPost
{
    /**
     * @param BlogSection[] $sections
     */
    public function __construct(
        public string $title,
        public array $sections,
    ) {
    }

    /**
     * @return array{title: string, sections: array<int, array{heading: string, body: string}>}
     */
    public function toArray(): array
    {
        return [
            'title'    => $this->title,
            'sections' => array_map(fn (BlogSection $s) => $s->toArray(), $this->sections),
        ];
    }

    /**
     * @param array{title: string, sections: array<int, array{heading: string, body: string}>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            sections: array_map(
                fn (array $s) => BlogSection::fromArray($s),
                $data['sections'],
            ),
        );
    }
}
