<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class HighlightMoment
{
    public function __construct(
        public string $timecode,
        public string $title,
        public string $whyNotable,
        public string $category = 'insight',
    ) {
    }

    /**
     * @return array{timecode: string, title: string, why_notable: string, category: string}
     */
    public function toArray(): array
    {
        return [
            'timecode'    => $this->timecode,
            'title'       => $this->title,
            'why_notable' => $this->whyNotable,
            'category'    => $this->category,
        ];
    }

    /**
     * @param array{timecode: string, title: string, why_notable: string, category?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            timecode: $data['timecode'],
            title: $data['title'],
            whyNotable: $data['why_notable'],
            category: $data['category'] ?? 'insight',
        );
    }
}
