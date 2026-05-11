<?php

declare(strict_types=1);

use App\Domain\ValueObjects\SummaryKeyPoint;

it('creates a SummaryKeyPoint with valid data', function () {
    $point = new SummaryKeyPoint('01:30', 'Service Providers', 'How to register bindings.');

    expect($point->timecode)->toBe('01:30')
        ->and($point->title)->toBe('Service Providers')
        ->and($point->details)->toBe('How to register bindings.');
});

it('serializes to array via toArray()', function () {
    $point = new SummaryKeyPoint('05:00', 'Eloquent Tips', 'Avoid N+1 queries.');

    expect($point->toArray())->toBe([
        'timecode' => '05:00',
        'title'    => 'Eloquent Tips',
        'details'  => 'Avoid N+1 queries.',
    ]);
});

it('deserializes from array via fromArray()', function () {
    $point = SummaryKeyPoint::fromArray([
        'timecode' => '10:15',
        'title'    => 'Testing',
        'details'  => 'Write tests first.',
    ]);

    expect($point->timecode)->toBe('10:15')
        ->and($point->title)->toBe('Testing')
        ->and($point->details)->toBe('Write tests first.');
});
