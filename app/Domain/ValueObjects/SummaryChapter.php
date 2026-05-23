<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class SummaryChapter
{
    public function __construct(
        public string $title,
        public string $startTimecode,
        public string $endTimecode,
    ) {
    }

    /**
     * @return array{title: string, start_timecode: string, end_timecode: string}
     */
    public function toArray(): array
    {
        return [
            'title'          => $this->title,
            'start_timecode' => $this->startTimecode,
            'end_timecode'   => $this->endTimecode,
        ];
    }

    /**
     * @param array{title: string, start_timecode: string, end_timecode: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            startTimecode: $data['start_timecode'],
            endTimecode: $data['end_timecode'],
        );
    }
}
