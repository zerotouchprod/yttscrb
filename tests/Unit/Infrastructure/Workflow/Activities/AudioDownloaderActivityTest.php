<?php

declare(strict_types=1);

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Domain\ValueObjects\AudioFile;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Workflow\Activities\AudioDownloaderActivity;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
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

it('downloads audio through audio extractor port and returns typed result', function (): void {
    $extractor = new class implements AudioExtractorInterface {
        public function extract(YouTubeUrl $youtubeUrl): AudioFile
        {
            return new AudioFile('/tmp/task-123.mp3');
        }
    };

    Container::getInstance()->instance(AudioExtractorInterface::class, $extractor);

    $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
    $activity = new AudioDownloaderActivity(0, 'now', $this->storedWorkflow, 'task-123', $url);

    $result = $activity->execute('task-123', $url);

    expect($result)->toBeInstanceOf(DownloadedAudioResult::class)
        ->and($result->path)->toBe('/tmp/task-123.mp3');
});

it('passes the youtube url to the audio extractor', function (): void {
    $extractor = new class implements AudioExtractorInterface {
        public ?YouTubeUrl $receivedUrl = null;

        public function extract(YouTubeUrl $youtubeUrl): AudioFile
        {
            $this->receivedUrl = $youtubeUrl;

            return new AudioFile('/tmp/audio.mp3');
        }
    };

    Container::getInstance()->instance(AudioExtractorInterface::class, $extractor);

    $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
    $activity = new AudioDownloaderActivity(0, 'now', $this->storedWorkflow, 'task-123', $url);
    $activity->execute('task-123', $url);

    expect($extractor->receivedUrl)->toBeInstanceOf(YouTubeUrl::class)
        ->and($extractor->receivedUrl->value())->toBe($url);
});
