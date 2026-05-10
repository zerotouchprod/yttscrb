<?php

declare(strict_types=1);

use App\Application\Ports\Output\SubtitleProviderInterface;
use App\Infrastructure\Workflow\Activities\SubtitleExtractorActivity;
use Illuminate\Container\Container;
use Workflow\Models\StoredWorkflow;

beforeEach(function (): void {
    $storedWorkflow = Mockery::mock(StoredWorkflow::class);
    $storedWorkflow->shouldReceive('workflowOptions')->andReturn(new \Workflow\WorkflowOptions());
    $storedWorkflow->shouldReceive('effectiveConnection')->andReturn(null);
    $storedWorkflow->shouldReceive('effectiveQueue')->andReturn(null);
    $storedWorkflow->shouldReceive('hasLogByIndex')->andReturn(false);
    $storedWorkflow->shouldReceive('id')->andReturn(1);
    $this->storedWorkflow = $storedWorkflow;
});

afterEach(function (): void {
    Mockery::close();
});

it('returns subtitle text from subtitle provider', function (): void {
    $provider = new class implements SubtitleProviderInterface {
        /** @phpstan-ignore return.unusedType */
        public function extract(string $youtubeUrl): ?string
        {
            return 'subtitle text';
        }
    };

    Container::getInstance()->instance(SubtitleProviderInterface::class, $provider);

    $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
    $activity = new SubtitleExtractorActivity(0, 'now', $this->storedWorkflow, $url);

    expect($activity->execute($url))->toBe('subtitle text');
});

it('returns null when subtitle provider returns null', function (): void {
    $provider = new class implements SubtitleProviderInterface {
        public function extract(string $youtubeUrl): ?string
        {
            return null;
        }
    };

    Container::getInstance()->instance(SubtitleProviderInterface::class, $provider);

    $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
    $activity = new SubtitleExtractorActivity(0, 'now', $this->storedWorkflow, $url);

    expect($activity->execute($url))->toBeNull();
});

it('passes the youtube url to the subtitle provider', function (): void {
    $provider = new class implements SubtitleProviderInterface {
        public string $receivedUrl = '';

        /** @phpstan-ignore return.unusedType */
        public function extract(string $youtubeUrl): ?string
        {
            $this->receivedUrl = $youtubeUrl;

            return 'some text';
        }
    };

    Container::getInstance()->instance(SubtitleProviderInterface::class, $provider);

    $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
    $activity = new SubtitleExtractorActivity(0, 'now', $this->storedWorkflow, $url);
    $activity->execute($url);

    expect($provider->receivedUrl)->toBe($url);
});
