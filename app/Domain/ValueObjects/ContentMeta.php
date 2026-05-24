<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class ContentMeta
{
    public function __construct(
        public string $complexity,
        public int $readingTimeMinutes,
        public string $jargonDensity,
        public string $targetAudience,
    ) {
    }

    /**
     * @return array{complexity: string, reading_time_minutes: int, jargon_density: string, target_audience: string}
     */
    public function toArray(): array
    {
        return [
            'complexity'           => $this->complexity,
            'reading_time_minutes' => $this->readingTimeMinutes,
            'jargon_density'       => $this->jargonDensity,
            'target_audience'      => $this->targetAudience,
        ];
    }

    /**
     * @param array{complexity: string, reading_time_minutes: int, jargon_density: string, target_audience: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            complexity: $data['complexity'],
            readingTimeMinutes: (int) $data['reading_time_minutes'],
            jargonDensity: $data['jargon_density'],
            targetAudience: $data['target_audience'],
        );
    }
}
