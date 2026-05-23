<?php

declare(strict_types=1);

use App\Application\DTO\SendFeedbackCommand;
use App\Application\Ports\Output\FeedbackNotifierInterface;

beforeEach(function (): void {
    $this->mockNotifier = mock(FeedbackNotifierInterface::class);
    $this->app->instance(FeedbackNotifierInterface::class, $this->mockNotifier);
});

it('sends feedback successfully', function (): void {
    $this->mockNotifier
        ->shouldReceive('notify')
        ->once()
        ->with(Mockery::type(SendFeedbackCommand::class))
        ->andReturnNull();

    $response = $this->postJson('/api/feedback', [
        'message' => 'Great app!',
        'email' => 'user@example.com',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Thank you for your feedback!']);
});

it('sends feedback without email', function (): void {
    $this->mockNotifier
        ->shouldReceive('notify')
        ->once()
        ->with(Mockery::type(SendFeedbackCommand::class));

    $response = $this->postJson('/api/feedback', [
        'message' => 'Nice work!',
    ]);

    $response->assertOk();
});

it('validates message is required', function (): void {
    $response = $this->postJson('/api/feedback', [
        'email' => 'user@example.com',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

it('validates message max length', function (): void {
    $response = $this->postJson('/api/feedback', [
        'message' => str_repeat('a', 2001),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

it('validates email format', function (): void {
    $response = $this->postJson('/api/feedback', [
        'message' => 'Test',
        'email' => 'not-an-email',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});
