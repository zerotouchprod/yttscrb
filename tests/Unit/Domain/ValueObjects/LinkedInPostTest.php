<?php

declare(strict_types=1);

use App\Domain\ValueObjects\LinkedInPost;

it('creates a LinkedInPost with hook, body and callToAction', function (): void {
    $post = new LinkedInPost(
        hook: 'Most teams ship slow. Here is why.',
        body: 'The real problem is not the code.',
        callToAction: 'Full summary → [URL]',
    );

    expect($post->hook())->toBe('Most teams ship slow. Here is why.')
        ->and($post->body())->toBe('The real problem is not the code.')
        ->and($post->callToAction())->toBe('Full summary → [URL]');
});

it('serializes LinkedInPost to array', function (): void {
    $post = new LinkedInPost(
        hook: 'Hook text',
        body: 'Body text',
        callToAction: 'CTA → [URL]',
    );

    expect($post->toArray())->toBe([
        'hook'           => 'Hook text',
        'body'           => 'Body text',
        'call_to_action' => 'CTA → [URL]',
    ]);
});

it('deserializes LinkedInPost from array', function (): void {
    $data = [
        'hook'           => 'Deserialized hook',
        'body'           => 'Deserialized body',
        'call_to_action' => 'Deserialized CTA → [URL]',
    ];

    $post = LinkedInPost::fromArray($data);

    expect($post->hook())->toBe('Deserialized hook')
        ->and($post->body())->toBe('Deserialized body')
        ->and($post->callToAction())->toBe('Deserialized CTA → [URL]');
});

it('round-trips LinkedInPost through toArray and fromArray', function (): void {
    $original = new LinkedInPost(
        hook: 'The hook',
        body: 'The body paragraph one.' . "\n\n" . 'Paragraph two.',
        callToAction: 'Read more → [URL]',
    );

    $roundTrip = LinkedInPost::fromArray($original->toArray());

    expect($roundTrip->hook())->toBe($original->hook())
        ->and($roundTrip->body())->toBe($original->body())
        ->and($roundTrip->callToAction())->toBe($original->callToAction());
});

it('preserves newlines in body during round-trip', function (): void {
    $body = "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.";
    $post = new LinkedInPost('Hook', $body, 'CTA → [URL]');

    $restored = LinkedInPost::fromArray($post->toArray());

    expect($restored->body())->toBe($body);
});
