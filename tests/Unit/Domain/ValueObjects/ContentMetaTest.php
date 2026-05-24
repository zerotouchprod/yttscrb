<?php

declare(strict_types=1);

use App\Domain\ValueObjects\ContentMeta;

it('creates a valid content meta', function (): void {
    $meta = new ContentMeta(
        complexity: 'intermediate',
        readingTimeMinutes: 12,
        jargonDensity: 'moderate',
        targetAudience: 'Software developers with basic Kubernetes experience',
    );

    expect($meta->complexity)->toBe('intermediate')
        ->and($meta->readingTimeMinutes)->toBe(12)
        ->and($meta->jargonDensity)->toBe('moderate')
        ->and($meta->targetAudience)->toBe('Software developers with basic Kubernetes experience');
});

it('serializes to array via toArray()', function (): void {
    $meta = new ContentMeta(
        complexity: 'advanced',
        readingTimeMinutes: 25,
        jargonDensity: 'high',
        targetAudience: 'ML engineers',
    );

    expect($meta->toArray())->toBe([
        'complexity'           => 'advanced',
        'reading_time_minutes' => 25,
        'jargon_density'       => 'high',
        'target_audience'      => 'ML engineers',
    ]);
});

it('hydrates from array via fromArray()', function (): void {
    $meta = ContentMeta::fromArray([
        'complexity'           => 'beginner',
        'reading_time_minutes' => 5,
        'jargon_density'       => 'low',
        'target_audience'      => 'Anyone new to programming',
    ]);

    expect($meta->complexity)->toBe('beginner')
        ->and($meta->readingTimeMinutes)->toBe(5)
        ->and($meta->jargonDensity)->toBe('low')
        ->and($meta->targetAudience)->toBe('Anyone new to programming');
});

it('casts reading_time_minutes to int in fromArray', function (): void {
    $meta = ContentMeta::fromArray([
        'complexity'           => 'expert',
        'reading_time_minutes' => 42,
        'jargon_density'       => 'high',
        'target_audience'      => 'Experts',
    ]);

    expect($meta->readingTimeMinutes)->toBe(42)
        ->and($meta->readingTimeMinutes)->toBeInt();
});
