<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\ClickbaitVerdict;
use PHPUnit\Framework\TestCase;

final class ClickbaitVerdictTest extends TestCase
{
    public function test_creates_valid_verdict(): void
    {
        $verdict = new ClickbaitVerdict(score: 85, comment: 'The title accurately reflects the content.');

        $this->assertSame(85, $verdict->score);
        $this->assertSame('The title accurately reflects the content.', $verdict->comment);
    }

    public function test_score_clamped_to_zero(): void
    {
        $verdict = new ClickbaitVerdict(score: -5, comment: 'test');
        $this->assertSame(0, $verdict->score);
    }

    public function test_score_clamped_to_hundred(): void
    {
        $verdict = new ClickbaitVerdict(score: 150, comment: 'test');
        $this->assertSame(100, $verdict->score);
    }

    public function test_to_array_output(): void
    {
        $verdict = new ClickbaitVerdict(42, 'Mostly legit.');

        $this->assertSame([
            'score'   => 42,
            'comment' => 'Mostly legit.',
        ], $verdict->toArray());
    }

    public function test_from_array_hydration(): void
    {
        $verdict = ClickbaitVerdict::fromArray(['score' => 95, 'comment' => 'Legit.']);

        $this->assertSame(95, $verdict->score);
        $this->assertSame('Legit.', $verdict->comment);
    }
}
