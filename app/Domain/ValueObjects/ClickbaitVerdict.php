<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class ClickbaitVerdict
{
    public int $score;
    public string $comment;

    public function __construct(int $score, string $comment)
    {
        $this->score = max(0, min(100, $score));
        $this->comment = $comment;
    }

    /**
     * @return array{score: int, comment: string}
     */
    public function toArray(): array
    {
        return [
            'score'   => $this->score,
            'comment' => $this->comment,
        ];
    }

    /**
     * @param array{score: int, comment: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            score: $data['score'],
            comment: $data['comment'],
        );
    }
}
