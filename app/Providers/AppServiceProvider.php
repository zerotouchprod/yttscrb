<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\SubtitleProviderInterface;
use App\Application\Ports\Output\SummaryProviderInterface;
use App\Application\Ports\Output\TaxonomyRepositoryInterface;
use App\Application\Ports\Output\TranscriptionProviderInterface;
use App\Application\Ports\Output\FeedbackNotifierInterface;
use App\Application\Ports\Output\ViewTrackerInterface;
use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Infrastructure\Adapters\Output\Persistence\MediaTaskEloquentRepository;
use App\Infrastructure\Adapters\Output\Persistence\TaxonomyEloquentRepository;
use App\Infrastructure\Adapters\Output\Summary\LaravelAiSummaryAdapter;
use App\Infrastructure\Adapters\Output\Telegram\TelegramFeedbackNotifier;
use App\Infrastructure\Adapters\Output\Transcription\GroqWhisperAdapter;
use App\Infrastructure\Adapters\Output\Transcription\SubtitleExtractorAdapter;
use App\Infrastructure\Adapters\Output\Views\RedisViewTracker;
use App\Infrastructure\Adapters\Output\Workflow\WorkflowDispatcher;
use App\Infrastructure\Adapters\Output\YoutubeDl\YoutubeDlAudioExtractor;
use App\Infrastructure\Workflow\WorkflowStarter;
use App\Infrastructure\Workflow\DurableWorkflowStarter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AudioExtractorInterface::class, function ($app) {
            $ytDlpBinary = config('services.yt_dlp_binary', 'yt-dlp');
            $ipv6Prefix = config('services.youtube.ipv6_prefix');

            return new YoutubeDlAudioExtractor(
                binaryPath: is_string($ytDlpBinary) && $ytDlpBinary !== '' ? $ytDlpBinary : 'yt-dlp',
                outputDir: storage_path('app/temp'),
                ipv6Prefix: is_string($ipv6Prefix) && $ipv6Prefix !== '' ? $ipv6Prefix : null,
            );
        });
        $this->app->bind(MediaTaskRepositoryInterface::class, MediaTaskEloquentRepository::class);
        $this->app->bind(SubtitleProviderInterface::class, function ($app) {
            $ipv6Prefix = config('services.youtube.ipv6_prefix');

            return new SubtitleExtractorAdapter(
                ipv6Prefix: is_string($ipv6Prefix) && $ipv6Prefix !== '' ? $ipv6Prefix : null,
            );
        });
        $this->app->bind(TranscriptionProviderInterface::class, GroqWhisperAdapter::class);
        $this->app->bind(SummaryProviderInterface::class, LaravelAiSummaryAdapter::class);
        $this->app->bind(WorkflowDispatcherInterface::class, WorkflowDispatcher::class);
        $this->app->bind(WorkflowStarter::class, DurableWorkflowStarter::class);
        $this->app->bind(FeedbackNotifierInterface::class, TelegramFeedbackNotifier::class);
        $this->app->bind(TaxonomyRepositoryInterface::class, TaxonomyEloquentRepository::class);
        $this->app->bind(ViewTrackerInterface::class, RedisViewTracker::class);
    }

    public function boot(): void
    {
        //
    }
}
