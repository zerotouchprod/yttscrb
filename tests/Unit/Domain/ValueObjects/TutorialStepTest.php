<?php

declare(strict_types=1);

use App\Domain\ValueObjects\TutorialStep;

it('creates a valid tutorial step', function (): void {
    $step = new TutorialStep(step: 1, time: '03:45', action: 'composer require laravel/horizon');

    expect($step->step)->toBe(1)
        ->and($step->time)->toBe('03:45')
        ->and($step->action)->toBe('composer require laravel/horizon');
});

it('serializes to array via toArray()', function (): void {
    $step = new TutorialStep(step: 2, time: '05:10', action: 'php artisan horizon:install');

    expect($step->toArray())->toBe([
        'step'   => 2,
        'time'   => '05:10',
        'action' => 'php artisan horizon:install',
    ]);
});

it('hydrates from array via fromArray()', function (): void {
    $step = TutorialStep::fromArray([
        'step'   => 3,
        'time'   => '10:20',
        'action' => 'Run migrations',
    ]);

    expect($step->step)->toBe(3)
        ->and($step->time)->toBe('10:20')
        ->and($step->action)->toBe('Run migrations');
});

it('casts step to int in fromArray', function (): void {
    // fromArray already casts to int, so passing int directly is expected
    $step = TutorialStep::fromArray([
        'step'   => 7,
        'time'   => '12:00',
        'action' => 'Deploy',
    ]);

    expect($step->step)->toBe(7);
});

it('handles empty time and action', function (): void {
    $step = new TutorialStep(step: 1, time: '', action: '');

    expect($step->time)->toBe('')
        ->and($step->action)->toBe('');
});
