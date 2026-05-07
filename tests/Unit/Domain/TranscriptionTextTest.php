<?php

use App\Domain\ValueObjects\TranscriptionText;

it('stores non-empty transcript text', function (): void {
    $text = new TranscriptionText('Hello world');

    expect($text->value())->toBe('Hello world')
        ->and($text->wordCount())->toBe(2);
});

it('rejects empty transcript text', function (): void {
    new TranscriptionText('   ');
})->throws(\InvalidArgumentException::class);
