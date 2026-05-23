<?php

declare(strict_types=1);

use App\Domain\ValueObjects\SummaryChapter;
use App\Domain\ValueObjects\SummaryKeyPoint;
use App\Domain\ValueObjects\SummaryResult;
use App\Domain\ValueObjects\TutorialStep;

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
        'introduction'      => 'Introduction text',
        'key_points'        => [
            ['timecode' => '03:15', 'title' => 'Setup', 'details' => 'How to set up.'],
        ],
        'conclusion'        => 'Closing remark',
        'resources'         => [],
        'clickbait_verdict' => null,
        'tutorial_steps'    => [],
        'chapters'          => [],
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
        ->and($roundTrip->keyPoints()[0]->title)->toBe('Chapter')
        ->and($roundTrip->resources())->toBe([])
        ->and($roundTrip->clickbaitVerdict())->toBeNull()
        ->and($roundTrip->tutorialSteps())->toBe([]);
});

it('stores and retrieves tutorial steps', function (): void {
    $step = new TutorialStep(step: 1, time: '01:00', action: 'Open settings');
    $result = new SummaryResult('Intro', [], tutorialSteps: [$step]);

    expect($result->tutorialSteps())->toHaveCount(1)
        ->and($result->tutorialSteps()[0]->step)->toBe(1)
        ->and($result->tutorialSteps()[0]->time)->toBe('01:00')
        ->and($result->tutorialSteps()[0]->action)->toBe('Open settings');
});

it('defaults tutorialSteps to empty array', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->tutorialSteps())->toBe([]);
});

it('stores and retrieves chapters', function (): void {
    $chapter = new SummaryChapter(
        title: 'Introduction',
        startTimecode: '00:00',
        endTimecode: '05:30',
    );
    $result = new SummaryResult('Intro', [], chapters: [$chapter]);

    expect($result->chapters())->toHaveCount(1)
        ->and($result->chapters()[0]->title)->toBe('Introduction')
        ->and($result->chapters()[0]->startTimecode)->toBe('00:00')
        ->and($result->chapters()[0]->endTimecode)->toBe('05:30');
});

it('defaults chapters to empty array', function (): void {
    $result = new SummaryResult('Intro', []);

    expect($result->chapters())->toBe([]);
});

it('serializes chapters in toArray', function (): void {
    $chapter = new SummaryChapter(
        title: 'Core',
        startTimecode: '05:30',
        endTimecode: '12:00',
    );
    $result = new SummaryResult('Intro', [], chapters: [$chapter]);

    expect($result->toArray()['chapters'])->toBe([
        ['title' => 'Core', 'start_timecode' => '05:30', 'end_timecode' => '12:00'],
    ]);
});

it('deserializes chapters from fromArray', function (): void {
    $data = [
        'introduction' => 'Intro',
        'key_points'   => [],
        'chapters'     => [
            ['title' => 'Wrap Up', 'start_timecode' => '12:00', 'end_timecode' => '15:00'],
        ],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->chapters())->toHaveCount(1)
        ->and($result->chapters()[0]->title)->toBe('Wrap Up')
        ->and($result->chapters()[0]->startTimecode)->toBe('12:00')
        ->and($result->chapters()[0]->endTimecode)->toBe('15:00');
});

it('fromArray handles missing chapters key', function (): void {
    $data = [
        'introduction' => 'No chapters',
        'key_points'   => [],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->chapters())->toBe([]);
});

it('round-trips chapters through toArray and fromArray', function (): void {
    $chapter = new SummaryChapter(
        title: 'Setup',
        startTimecode: '00:00',
        endTimecode: '03:00',
    );
    $original = new SummaryResult('Intro', [], chapters: [$chapter]);

    $roundTrip = SummaryResult::fromArray($original->toArray());

    expect($roundTrip->chapters())->toHaveCount(1)
        ->and($roundTrip->chapters()[0]->title)->toBe('Setup')
        ->and($roundTrip->chapters()[0]->startTimecode)->toBe('00:00')
        ->and($roundTrip->chapters()[0]->endTimecode)->toBe('03:00');
});

it('serializes tutorial steps in toArray', function (): void {
    $step = new TutorialStep(step: 1, time: '02:00', action: 'Run npm install');
    $result = new SummaryResult('Intro', [], tutorialSteps: [$step]);

    expect($result->toArray()['tutorial_steps'])->toBe([
        ['step' => 1, 'time' => '02:00', 'action' => 'Run npm install'],
    ]);
});

it('deserializes tutorial steps from fromArray', function (): void {
    $data = [
        'introduction'   => 'Intro',
        'key_points'     => [],
        'tutorial_steps' => [
            ['step' => 1, 'time' => '03:00', 'action' => 'Do something'],
        ],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->tutorialSteps())->toHaveCount(1)
        ->and($result->tutorialSteps()[0]->step)->toBe(1)
        ->and($result->tutorialSteps()[0]->time)->toBe('03:00')
        ->and($result->tutorialSteps()[0]->action)->toBe('Do something');
});

it('fromArray handles missing tutorial_steps', function (): void {
    $data = [
        'introduction' => 'No tutorial here',
        'key_points'   => [],
    ];

    $result = SummaryResult::fromArray($data);

    expect($result->tutorialSteps())->toBe([]);
});
