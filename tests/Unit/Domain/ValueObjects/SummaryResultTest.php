<?php

declare(strict_types=1);

use App\Domain\ValueObjects\SummaryKeyPoint;
use App\Domain\ValueObjects\SummaryResult;

it('stores introduction, keyPoints and conclusion', function (): void {
    $kp = new SummaryKeyPoint('01:30', 'Title', 'Details');
    $result = new SummaryResult('Intro text', [$kp], 'Final thought');

    expect($result->introduction())->toBe('Intro text')
        ->and($result->keyPoints())->toHaveCount(1)
        ->and($result->keyPoints()[0])->toBe($kp)
        ->and($result->conclusion())->toBe('Final thought');
});

it('defaults conclusion to null', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->conclusion())->toBeNull();
});

it('serializes to array via toArray()', function (): void {
    $kp = new SummaryKeyPoint('03:15', 'Setup', 'How to set up.');
    $result = new SummaryResult('Introduction text', [$kp], 'Closing remark');

    expect($result->toArray())->toBe([
        'introduction' => 'Introduction text',
        'key_points'   => [
            ['timecode' => '03:15', 'title' => 'Setup', 'details' => 'How to set up.'],
        ],
        'conclusion' => 'Closing remark',
    ]);
});

it('serializes with null conclusion', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->toArray()['conclusion'])->toBeNull();
});

it('deserializes from array via fromArray()', function (): void {
    $data = [
        'introduction' => 'Hello world',
        'key_points'   => [
            ['timecode' => '00:30', 'title' => 'Start', 'details' => 'First point.'],
        ],
        'conclusion'  => 'Done.',
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->introduction())->toBe('Hello world')
        ->and($result->keyPoints())->toHaveCount(1)
        ->and($result->keyPoints()[0]->timecode)->toBe('00:30')
        ->and($result->conclusion())->toBe('Done.');
});

it('fromArray handles missing conclusion key', function (): void {
    $data = [
        'introduction' => 'No conclusion here',
        'key_points'   => [],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->conclusion())->toBeNull();
});

it('round-trips through toArray and fromArray', function (): void {
    $kp = new SummaryKeyPoint('10:00', 'Chapter', 'Long detail.');
    $original = new SummaryResult('Into text', [$kp], 'Summary end');

    $roundTrip = SummaryResult::fromArray($original->toArray());

    expect($roundTrip->introduction())->toBe($original->introduction())
        ->and($roundTrip->conclusion())->toBe($original->conclusion())
        ->and($roundTrip->keyPoints()[0]->timecode)->toBe('10:00')
        ->and($roundTrip->keyPoints()[0]->title)->toBe('Chapter');
});
