<?php

declare(strict_types=1);

use App\Domain\ValueObjects\HighlightMoment;

it('creates a valid highlight moment', function (): void {
    $highlight = new HighlightMoment(
        timecode: '00:12:34',
        title: 'The big reveal',
        whyNotable: 'Speaker unexpectedly announces open-source.',
        category: 'surprise',
    );

    expect($highlight->timecode)->toBe('00:12:34')
        ->and($highlight->title)->toBe('The big reveal')
        ->and($highlight->whyNotable)->toBe('Speaker unexpectedly announces open-source.')
        ->and($highlight->category)->toBe('surprise');
});

it('defaults category to insight', function (): void {
    $highlight = new HighlightMoment(
        timecode: '00:05:00',
        title: 'Key insight',
        whyNotable: 'Important takeaway.',
    );

    expect($highlight->category)->toBe('insight');
});

it('serializes to array via toArray()', function (): void {
    $highlight = new HighlightMoment(
        timecode: '01:30',
        title: 'Funny moment',
        whyNotable: 'Audience laughs.',
        category: 'humor',
    );

    expect($highlight->toArray())->toBe([
        'timecode'    => '01:30',
        'title'       => 'Funny moment',
        'why_notable' => 'Audience laughs.',
        'category'    => 'humor',
    ]);
});

it('hydrates from array via fromArray()', function (): void {
    $highlight = HighlightMoment::fromArray([
        'timecode'    => '10:00',
        'title'       => 'Mic drop',
        'why_notable' => 'Perfect closing statement.',
        'category'    => 'quote',
    ]);

    expect($highlight->timecode)->toBe('10:00')
        ->and($highlight->title)->toBe('Mic drop')
        ->and($highlight->whyNotable)->toBe('Perfect closing statement.')
        ->and($highlight->category)->toBe('quote');
});

it('fromArray defaults category to insight', function (): void {
    $highlight = HighlightMoment::fromArray([
        'timecode'    => '05:00',
        'title'       => 'Good point',
        'why_notable' => 'Worth noting.',
    ]);

    expect($highlight->category)->toBe('insight');
});
