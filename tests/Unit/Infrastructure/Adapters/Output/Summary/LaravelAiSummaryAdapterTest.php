<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Adapters\Output\Summary;

use App\Ai\Agents\YoutubeSummarizerAgent;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\SummaryResult;
use App\Domain\ValueObjects\TranscriptionText;
use App\Infrastructure\Adapters\Output\Summary\LaravelAiSummaryAdapter;
use App\Shared\Exceptions\SummaryFailedException;
use Laravel\Ai\Gateway\FakeTextGateway;
use Tests\TestCase;

uses(TestCase::class);

it('returns SummaryResult when agent responds with structured data', function (): void {
    YoutubeSummarizerAgent::fake([
        [
            'introduction' => 'This video covers PHP testing.',
            'key_points'   => [
                ['timecode' => '01:00', 'title' => 'Setup', 'details' => 'Install dependencies.'],
                ['timecode' => '05:30', 'title' => 'Write tests', 'details' => 'Use Pest.'],
            ],
            'conclusion' => 'TDD improves quality.',
        ],
    ]);

    $adapter = new LaravelAiSummaryAdapter();

    $result = $adapter->summarize(
        new TranscriptionText('Transcript content here.'),
        new SummaryOptions(),
    );

    expect($result)->toBeInstanceOf(SummaryResult::class)
        ->and($result->introduction())->toBe('This video covers PHP testing.')
        ->and($result->keyPoints())->toHaveCount(2)
        ->and($result->keyPoints()[0]->timecode)->toBe('01:00')
        ->and($result->keyPoints()[0]->title)->toBe('Setup')
        ->and($result->keyPoints()[1]->timecode)->toBe('05:30')
        ->and($result->conclusion())->toBe('TDD improves quality.');
});

it('returns SummaryResult with null conclusion when omitted', function (): void {
    YoutubeSummarizerAgent::fake([
        [
            'introduction' => 'Brief intro.',
            'key_points'   => [],
            // no 'conclusion' key
        ],
    ]);

    $adapter = new LaravelAiSummaryAdapter();
    $result = $adapter->summarize(new TranscriptionText('Text'), new SummaryOptions());

    expect($result->conclusion())->toBeNull()
        ->and($result->keyPoints())->toBeEmpty();
});

it('wraps RuntimeException into SummaryFailedException when prompt fails', function (): void {
    // Fake with a closure that throws
    YoutubeSummarizerAgent::fake(
        fn () => throw new \RuntimeException('Network timeout')
    );

    $adapter = new LaravelAiSummaryAdapter();

    expect(fn () => $adapter->summarize(new TranscriptionText('Text'), new SummaryOptions()))
        ->toThrow(SummaryFailedException::class);
});
