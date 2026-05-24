<?php

declare(strict_types=1);

use App\Domain\ValueObjects\Flashcard;

it('creates a valid flashcard', function (): void {
    $card = new Flashcard(
        question: 'What is the Dependency Rule?',
        answer: 'Source code dependencies can only point inwards.',
        sourceTimecode: '00:05:30',
        difficulty: 'medium',
    );

    expect($card->question)->toBe('What is the Dependency Rule?')
        ->and($card->answer)->toBe('Source code dependencies can only point inwards.')
        ->and($card->sourceTimecode)->toBe('00:05:30')
        ->and($card->difficulty)->toBe('medium');
});

it('defaults difficulty to medium', function (): void {
    $card = new Flashcard(
        question: 'Q?',
        answer: 'A.',
        sourceTimecode: '00:01:00',
    );

    expect($card->difficulty)->toBe('medium');
});

it('serializes to array via toArray()', function (): void {
    $card = new Flashcard(
        question: 'What is SOLID?',
        answer: 'Five design principles.',
        sourceTimecode: '03:00',
        difficulty: 'easy',
    );

    expect($card->toArray())->toBe([
        'question'        => 'What is SOLID?',
        'answer'          => 'Five design principles.',
        'source_timecode' => '03:00',
        'difficulty'      => 'easy',
    ]);
});

it('hydrates from array via fromArray()', function (): void {
    $card = Flashcard::fromArray([
        'question'        => 'What is DI?',
        'answer'          => 'Dependency Injection.',
        'source_timecode' => '08:00',
        'difficulty'      => 'hard',
    ]);

    expect($card->question)->toBe('What is DI?')
        ->and($card->answer)->toBe('Dependency Injection.')
        ->and($card->sourceTimecode)->toBe('08:00')
        ->and($card->difficulty)->toBe('hard');
});

it('fromArray defaults difficulty to medium', function (): void {
    $card = Flashcard::fromArray([
        'question'        => 'Q',
        'answer'          => 'A',
        'source_timecode' => '00:30',
    ]);

    expect($card->difficulty)->toBe('medium');
});
