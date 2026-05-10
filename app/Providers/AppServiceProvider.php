<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\SubtitleProviderInterface;
use App\Application\Ports\Output\SummaryProviderInterface;
use App\Application\Ports\Output\TranscriptionProviderInterface;
use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Infrastructure\Adapters\Output\Persistence\MediaTaskEloquentRepository;
use App\Infrastructure\Adapters\Output\Summary\OpenAiSummaryAdapter;
use App\Infrastructure\Adapters\Output\Transcription\GroqWhisperAdapter;
use App\Infrastructure\Adapters\Output\Transcription\SubtitleExtractorAdapter;
use App\Infrastructure\Adapters\Output\Workflow\WorkflowDispatcher;
use App\Infrastructure\Adapters\Output\YoutubeDl\YoutubeDlAudioExtractor;
use App\Infrastructure\Workflow\WorkflowStarter;
use App\Infrastructure\Workflow\DurableWorkflowStarter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AudioExtractorInterface::class, YoutubeDlAudioExtractor::class);
        $this->app->bind(MediaTaskRepositoryInterface::class, MediaTaskEloquentRepository::class);
        $this->app->bind(SubtitleProviderInterface::class, SubtitleExtractorAdapter::class);
        $this->app->bind(TranscriptionProviderInterface::class, GroqWhisperAdapter::class);
        $this->app->bind(SummaryProviderInterface::class, OpenAiSummaryAdapter::class);
        $this->app->bind(WorkflowDispatcherInterface::class, WorkflowDispatcher::class);
        $this->app->bind(WorkflowStarter::class, DurableWorkflowStarter::class);
    }

    public function boot(): void
    {
        //
    }
}
