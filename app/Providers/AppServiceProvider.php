<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Application\Ports\Output\ExtractionAvailabilityCheckerInterface;
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
use App\Infrastructure\Adapters\Output\YoutubeDl\CookiesYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\Ipv6RotatedYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\Ipv6Rotator;
use App\Infrastructure\Adapters\Output\YoutubeDl\PrimaryYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\ProxyYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\StrategyCooldownAvailabilityChecker;
use App\Infrastructure\Adapters\Output\YoutubeDl\StrategyCooldownStore;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeAntiBotExtractionPolicy;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionErrorClassifier;
use App\Infrastructure\Adapters\Output\YoutubeDl\YoutubeDlAudioExtractor;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpProcessRunner;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpRateLimiter;
use App\Infrastructure\Workflow\WorkflowStarter;
use App\Infrastructure\Workflow\DurableWorkflowStarter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shared dependencies for anti-bot policy
        $this->app->singleton(YtDlpProcessRunner::class, function () {
            return new YtDlpProcessRunner(
                timeoutSec: (int) config('services.youtube.yt_dlp_timeout', 300),
            );
        });

        $this->app->singleton(YouTubeExtractionErrorClassifier::class);

        $this->app->singleton(YtDlpRateLimiter::class);

        $this->app->singleton(Ipv6Rotator::class);

        $this->app->singleton(StrategyCooldownStore::class, function () {
            return new StrategyCooldownStore(
                failureThreshold: (int) config('services.youtube.cooldown_failure_threshold', 3),
                cooldownDurationSec: (int) config('services.youtube.cooldown_duration_sec', 600),
                failureWindowSec: (int) config('services.youtube.cooldown_failure_window_sec', 120),
            );
        });

        // Build the strategy chain
        $this->app->singleton(YouTubeAntiBotExtractionPolicy::class, function ($app) {
            $ytDlpBinary = config('services.yt_dlp_binary', 'yt-dlp');
            $binaryPath = is_string($ytDlpBinary) && $ytDlpBinary !== '' ? $ytDlpBinary : 'yt-dlp';

            $ipv6Prefix = config('services.youtube.ipv6_prefix');
            $ipv6Prefix = is_string($ipv6Prefix) && $ipv6Prefix !== '' ? $ipv6Prefix : null;

            $cookiesPath = config('services.youtube.cookies_path');
            $cookiesPath = is_string($cookiesPath) && $cookiesPath !== '' && file_exists($cookiesPath) ? $cookiesPath : null;

            $strategies = [
                $app->make(PrimaryYtDlpStrategy::class, [
                    'binaryPath' => $binaryPath,
                ]),
            ];

            // Add proxy strategy only if configured
            $proxyUrl = config('services.youtube.proxy_url');
            $proxyUrl = is_string($proxyUrl) && $proxyUrl !== '' ? $proxyUrl : null;

            if ($proxyUrl !== null) {
                $strategies[] = $app->make(ProxyYtDlpStrategy::class, [
                    'binaryPath' => $binaryPath,
                    'proxyUrl' => $proxyUrl,
                ]);
            }

            // Add cookies strategy only if configured
            if ($cookiesPath !== null) {
                $strategies[] = $app->make(CookiesYtDlpStrategy::class, [
                    'binaryPath' => $binaryPath,
                    'cookiesPath' => $cookiesPath,
                ]);
            }

            // Add IPv6 strategy only if configured
            if ($ipv6Prefix !== null) {
                $strategies[] = $app->make(Ipv6RotatedYtDlpStrategy::class, [
                    'binaryPath' => $binaryPath,
                    'ipv6Prefix' => $ipv6Prefix,
                ]);
            }

            return new YouTubeAntiBotExtractionPolicy(
                strategies: $strategies,
                cooldownStore: $app->make(StrategyCooldownStore::class),
                maxRetriesPerStrategy: (int) config('services.youtube.retry_max_per_strategy', 2),
                retryCooldownSec: (int) config('services.youtube.retry_cooldown_sec', 90),
                transientRetryCooldownSec: (int) config('services.youtube.transient_retry_cooldown_sec', 10),
            );
        });

        // Audio extractor — now thin wrapper around policy
        $this->app->bind(AudioExtractorInterface::class, function ($app) {
            return new YoutubeDlAudioExtractor(
                policy: $app->make(YouTubeAntiBotExtractionPolicy::class),
                outputDir: storage_path('app/temp'),
            );
        });

        // Subtitle extractor — now thin wrapper around policy
        $this->app->bind(SubtitleProviderInterface::class, function ($app) {
            return new SubtitleExtractorAdapter(
                policy: $app->make(YouTubeAntiBotExtractionPolicy::class),
                outputDir: storage_path('app/temp/subs'),
            );
        });

        $this->app->bind(MediaTaskRepositoryInterface::class, MediaTaskEloquentRepository::class);
        $this->app->bind(TranscriptionProviderInterface::class, GroqWhisperAdapter::class);
        $this->app->bind(SummaryProviderInterface::class, LaravelAiSummaryAdapter::class);
        $this->app->bind(ExtractionAvailabilityCheckerInterface::class, StrategyCooldownAvailabilityChecker::class);
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
