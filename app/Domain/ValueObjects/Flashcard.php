<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class Flashcard
{
    public function __construct(
        public string $question,
        public string $answer,
        public string $sourceTimecode,
        public string $difficulty = 'medium',
    ) {
    }

    /**
     * @return array{question: string, answer: string, source_timecode: string, difficulty: string}
     */
    public function toArray(): array
    {
        return [
            'question'        => $this->question,
            'answer'          => $this->answer,
            'source_timecode' => $this->sourceTimecode,
            'difficulty'      => $this->difficulty,
        ];
    }

    /**
     * @param array{question: string, answer: string, source_timecode: string, difficulty?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            question: $data['question'],
            answer: $data['answer'],
            sourceTimecode: $data['source_timecode'],
            difficulty: $data['difficulty'] ?? 'medium',
        );
    }
}
