<?php

use App\Domain\ValueObjects\TranscriptionStatus;

it('identifies terminal statuses', function (): void {
    expect(TranscriptionStatus::Completed->isTerminal())->toBeTrue()
        ->and(TranscriptionStatus::Failed->isTerminal())->toBeTrue()
        ->and(TranscriptionStatus::Pending->isTerminal())->toBeFalse()
        ->and(TranscriptionStatus::Processing->isTerminal())->toBeFalse();
});

it('allows only valid lifecycle transitions', function (): void {
    expect(TranscriptionStatus::Pending->canTransitionTo(TranscriptionStatus::Processing))->toBeTrue()
        ->and(TranscriptionStatus::Pending->canTransitionTo(TranscriptionStatus::Completed))->toBeFalse()
        ->and(TranscriptionStatus::Processing->canTransitionTo(TranscriptionStatus::Completed))->toBeTrue()
        ->and(TranscriptionStatus::Processing->canTransitionTo(TranscriptionStatus::Failed))->toBeTrue()
        ->and(TranscriptionStatus::Completed->canTransitionTo(TranscriptionStatus::Failed))->toBeFalse()
        ->and(TranscriptionStatus::Failed->canTransitionTo(TranscriptionStatus::Processing))->toBeFalse();
});
