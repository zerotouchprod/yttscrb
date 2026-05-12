<?php

use App\Domain\ValueObjects\YouTubeUrl;

it('accepts youtube watch urls and extracts the video id', function (): void {
    $url = new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($url->value())->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($url->videoId()->value())->toBe('dQw4w9WgXcQ');
});

it('accepts youtu.be urls and extracts the video id', function (): void {
    $url = new YouTubeUrl('https://youtu.be/dQw4w9WgXcQ');

    expect($url->videoId()->value())->toBe('dQw4w9WgXcQ');
});

it('accepts mobile youtube urls and extracts the video id', function (): void {
    $url = new YouTubeUrl('https://m.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($url->videoId()->value())->toBe('dQw4w9WgXcQ');
});

it('accepts mobile youtube urls with extra query params', function (): void {
    $url = new YouTubeUrl('https://m.youtube.com/watch?v=GoRbmhW2zUw&pp=0gcJCTIE6IfKp2fp');

    expect($url->videoId()->value())->toBe('GoRbmhW2zUw');
});

it('rejects non youtube urls', function (): void {
    new YouTubeUrl('https://example.com/watch?v=dQw4w9WgXcQ');
})->throws(\InvalidArgumentException::class);
