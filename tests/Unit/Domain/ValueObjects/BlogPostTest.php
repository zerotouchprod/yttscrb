<?php

declare(strict_types=1);

use App\Domain\ValueObjects\BlogPost;
use App\Domain\ValueObjects\BlogSection;

it('creates a BlogSection with heading and body', function (): void {
    $section = new BlogSection('Setup', 'Install dependencies via composer.');

    expect($section->heading)->toBe('Setup')
        ->and($section->body)->toBe('Install dependencies via composer.');
});

it('serializes BlogSection to array', function (): void {
    $section = new BlogSection('Introduction', 'Welcome to the guide.');

    expect($section->toArray())->toBe([
        'heading' => 'Introduction',
        'body'    => 'Welcome to the guide.',
    ]);
});

it('deserializes BlogSection from array', function (): void {
    $data = ['heading' => 'Conclusion', 'body' => 'That wraps it up.'];

    $section = BlogSection::fromArray($data);

    expect($section->heading)->toBe('Conclusion')
        ->and($section->body)->toBe('That wraps it up.');
});

it('creates a BlogPost with title and sections', function (): void {
    $sections = [
        new BlogSection('Intro', 'Body text.'),
        new BlogSection('Main', 'More text.'),
    ];
    $post = new BlogPost('My Blog Title', $sections);

    expect($post->title)->toBe('My Blog Title')
        ->and($post->sections)->toHaveCount(2)
        ->and($post->sections[0]->heading)->toBe('Intro');
});

it('serializes BlogPost to array', function (): void {
    $sections = [
        new BlogSection('H1', 'B1'),
        new BlogSection('H2', 'B2'),
    ];
    $post = new BlogPost('Title', $sections);

    expect($post->toArray())->toBe([
        'title'    => 'Title',
        'sections' => [
            ['heading' => 'H1', 'body' => 'B1'],
            ['heading' => 'H2', 'body' => 'B2'],
        ],
    ]);
});

it('deserializes BlogPost from array', function (): void {
    $data = [
        'title'    => 'Deserialized Title',
        'sections' => [
            ['heading' => 'S1', 'body' => 'B1'],
            ['heading' => 'S2', 'body' => 'B2'],
        ],
    ];

    $post = BlogPost::fromArray($data);

    expect($post->title)->toBe('Deserialized Title')
        ->and($post->sections)->toHaveCount(2)
        ->and($post->sections[0]->heading)->toBe('S1')
        ->and($post->sections[1]->body)->toBe('B2');
});

it('round-trips BlogPost through toArray and fromArray', function (): void {
    $sections = [
        new BlogSection('Setup', 'How to begin.'),
        new BlogSection('Core', 'The main content.'),
        new BlogSection('Wrap', 'Final thoughts.'),
    ];
    $original = new BlogPost('Round Trip', $sections);

    $roundTrip = BlogPost::fromArray($original->toArray());

    expect($roundTrip->title)->toBe('Round Trip')
        ->and($roundTrip->sections)->toHaveCount(3)
        ->and($roundTrip->sections[1]->heading)->toBe('Core')
        ->and($roundTrip->sections[1]->body)->toBe('The main content.');
});

it('handles empty sections array', function (): void {
    $post = new BlogPost('No Sections', []);

    expect($post->sections)->toBe([])
        ->and($post->toArray()['sections'])->toBe([]);
});
