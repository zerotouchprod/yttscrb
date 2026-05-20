<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class SummaryKeyPoint
{
    public function __construct(
        public string $timecode,
        public string $title,
        public string $details,
    ) {
    }

    /**
     * @param array{timecode: string, title: string, details: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            timecode: $data['timecode'],
            title: $data['title'],
            details: $data['details'],
        );
    }

    /**
     * @internal Used only by persistence layer. HTTP serialization is handled by
     *           {@see \App\Infrastructure\Adapters\Input\Web\Resources\SummaryResource}.
     *
     * @return array{timecode: string, title: string, details: string}
     */
    public function toArray(): array
    {
        return [
            'timecode' => $this->timecode,
            'title'    => $this->title,
            'details'  => $this->details,
        ];
    }
}
