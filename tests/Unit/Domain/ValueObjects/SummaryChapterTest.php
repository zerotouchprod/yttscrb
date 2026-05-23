<?php

declare(strict_types=1);

use App\Domain\ValueObjects\SummaryChapter;

it('creates a valid summary chapter', function (): void {
    $chapter = new SummaryChapter(
        title: 'Introduction',
        startTimecode: '00:00:00',
        endTimecode: '00:05:30',
    );

    expect($chapter->title)->toBe('Introduction')
        ->and($chapter->startTimecode)->toBe('00:00:00')
        ->and($chapter->endTimecode)->toBe('00:05:30');
});

it('serializes to array via toArray()', function (): void {
    $chapter = new SummaryChapter(
        title: 'Core Content',
        startTimecode: '05:30',
        endTimecode: '12:45',
    );

    expect($chapter->toArray())->toBe([
        'title'          => 'Core Content',
        'start_timecode' => '05:30',
        'end_timecode'   => '12:45',
    ]);
});

it('hydrates from array via fromArray()', function (): void {
    $chapter = SummaryChapter::fromArray([
        'title'          => 'Conclusion',
        'start_timecode' => '12:45',
        'end_timecode'   => '15:00',
    ]);

    expect($chapter->title)->toBe('Conclusion')
        ->and($chapter->startTimecode)->toBe('12:45')
        ->and($chapter->endTimecode)->toBe('15:00');
});

it('handles short timecodes like MM:SS', function (): void {
    $chapter = new SummaryChapter(
        title: 'Quick Tip',
        startTimecode: '03:20',
        endTimecode: '05:10',
    );

    expect($chapter->toArray())->toBe([
        'title'          => 'Quick Tip',
        'start_timecode' => '03:20',
        'end_timecode'   => '05:10',
    ]);
});
