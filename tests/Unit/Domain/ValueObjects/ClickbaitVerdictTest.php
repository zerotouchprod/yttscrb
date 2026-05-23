<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\ClickbaitVerdict;
use PHPUnit\Framework\TestCase;

final class ClickbaitVerdictTest extends TestCase
{
    public function testCreatesValidVerdict(): void
    {
        $verdict = new ClickbaitVerdict(score: 85, comment: 'The title accurately reflects the content.');

        $this->assertSame(85, $verdict->score);
        $this->assertSame('The title accurately reflects the content.', $verdict->comment);
    }

    public function testScoreClampedToZero(): void
    {
        $verdict = new ClickbaitVerdict(score: -5, comment: 'test');
        $this->assertSame(0, $verdict->score);
    }

    public function testScoreClampedToHundred(): void
    {
        $verdict = new ClickbaitVerdict(score: 150, comment: 'test');
        $this->assertSame(100, $verdict->score);
    }

    public function testToArrayOutput(): void
    {
        $verdict = new ClickbaitVerdict(42, 'Mostly legit.');

        $this->assertSame([
            'score'   => 42,
            'comment' => 'Mostly legit.',
        ], $verdict->toArray());
    }

    public function testFromArrayHydration(): void
    {
        $verdict = ClickbaitVerdict::fromArray(['score' => 95, 'comment' => 'Legit.']);

        $this->assertSame(95, $verdict->score);
        $this->assertSame('Legit.', $verdict->comment);
    }
}
