<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\UseCases\TranscribeVideoHandler;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\YouTubeUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Searches YouTube via yt-dlp's built-in search (no API key required)
 * and dispatches transcription for top results.
 *
 * Uses: yt-dlp "ytsearch5:{query}" --dump-json
 *
 * This is the free, unlimited alternative to YouTube Data API v3.
 * Picks a random SEO query, fetches top 5 results, filters by
 * duration and already-processed status, and dispatches up to
 * 3 videos per run.
 */
final class SeedSearchContent extends Command
{
    protected $signature = 'app:seed-search';

    protected $description = 'Search YouTube via yt-dlp and dispatch transcription for top results. 1-3 videos per run.';

    /**
     * SEO-optimized search queries rotating randomly.
     *
     * Categories: tech how-to, reviews, comparisons, tutorials, beginner guides.
     * Avoids: music, politics, lifestyle (low-quality AI summaries).
     *
     * @var array<int, string>
     */
    private const QUERIES = [
        // Tech / Programming
        'how to fix kubernetes cluster',
        'docker compose tutorial 2026',
        'linux server setup guide',
        'python programming tutorial beginner',
        'rust vs go comparison',
        'react vs vue 2026',
        'typescript tutorial advanced',
        'aws vs azure vs gcp comparison',
        'terraform infrastructure as code tutorial',
        'github actions ci cd pipeline',
        'nginx reverse proxy setup',
        'postgresql performance tuning',
        'redis cache best practices',
        'system design interview guide',
        'clean architecture explained',
        'api design best practices',

        // Hardware / Reviews
        'macbook pro review 2026',
        'best laptop for programming 2026',
        'iphone vs android comparison',
        'best mechanical keyboard 2026',
        'raspberry pi projects beginner',
        'home server build guide',
        'best monitor for coding',
        'gaming pc build guide 2026',
        'wireless earbuds comparison',

        // Science / Education
        'quantum computing explained',
        'how neural networks work',
        'climate change science explained',
        'spacex starship update',
        'black holes explained physics',
        'how vaccines work',
        'evolution vs creationism debate',
        'mathematics for machine learning',
        'how blockchain works',
        'nuclear fusion breakthrough',

        // DIY / Maker
        '3d printer beginner guide',
        'arduino projects tutorial',
        'woodworking for beginners',
        'home automation setup',
        'solar panel installation guide',
        'diy electronics projects',
        'lego technic review',

        // Finance / Investing
        'index funds vs etf comparison',
        'how to start investing beginner',
        'bitcoin explained simple',
        'retirement planning guide',
        'real estate investing beginner',
    ];

    /** @var int Minimum video duration in seconds (2 min — excludes Shorts) */
    private const MIN_DURATION_SEC = 120;

    /** @var int Maximum video duration in seconds (3 hours) */
    private const MAX_DURATION_SEC = 10800;

    /** @var int Max videos to dispatch per run */
    private const MAX_DISPATCH = 3;

    public function __construct(
        private readonly MediaTaskRepositoryInterface $taskRepository,
        private readonly TranscribeVideoHandler $transcribeHandler,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = self::QUERIES[array_rand(self::QUERIES)];
        $this->line("Search query: \"{$query}\"");

        $videos = $this->searchVideos($query);
        $this->line('yt-dlp returned ' . count($videos) . ' results');

        $dispatched = 0;

        foreach ($videos as $video) {
            if ($dispatched >= self::MAX_DISPATCH) {
                break;
            }

            $videoId = $video['id'];
            $title = $video['title'];
            $duration = (int) $video['duration'];

            if ($videoId === '') {
                continue;
            }

            // Skip if already completed
            $vid = new \App\Domain\ValueObjects\VideoId($videoId);
            if ($this->taskRepository->findCompletedByVideoId($vid) !== null) {
                $this->line("  ∘ {$title} — already completed");
                continue;
            }

            // Skip if previous attempt failed permanently
            if ($this->hasPermanentFailure($videoId)) {
                $this->line("  ∘ {$title} — previously failed (permanent error)");
                continue;
            }

            // Filter by duration
            if ($duration > 0 && $duration < self::MIN_DURATION_SEC) {
                $this->line("  ∘ {$title} — too short ({$duration}s, likely a Short)");
                continue;
            }

            if ($duration > 0 && $duration > self::MAX_DURATION_SEC) {
                $this->line("  ∘ {$title} — too long ({$duration}s)");
                continue;
            }

            // Rate limit via Redis funnel
            Redis::funnel('groq-transcription')
                ->limit(3)
                ->releaseAfter(60)
                ->then(function () use ($videoId): void {
                    $url = new YouTubeUrl("https://youtube.com/watch?v={$videoId}");
                    $task = MediaTask::create((string) Str::uuid(), $url);
                    $this->transcribeHandler->handle($task);
                });

            $this->info("  ✓ Dispatched: {$title} ({$duration}s)");
            $dispatched++;
        }

        if ($dispatched === 0) {
            $this->info("No new videos to process for query: \"{$query}\"");
        }

        return self::SUCCESS;
    }

    private function hasPermanentFailure(string $videoId): bool
    {
        $task = \App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel::query()
            ->where('video_id', $videoId)
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->first();

        if ($task === null || $task->error_message === null) {
            return false;
        }

        $message = $task->error_message;

        $permanentPatterns = [
            'members-only',
            'members only',
            'private video',
            'too large',
            'too long',
            'not found or not readable',
        ];

        foreach ($permanentPatterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Search YouTube via yt-dlp built-in search.
     *
     * yt-dlp "ytsearch5:query" --dump-json returns 5 JSON objects
     * (one per line) with id, title, duration, etc.
     *
     * @return array<int, array{id: string, title: string, duration: string}>
     */
    private function searchVideos(string $query): array
    {
        $ytDlp = config('services.yt_dlp_binary', 'yt-dlp');

        if (! is_string($ytDlp) || $ytDlp === '') {
            $ytDlp = 'yt-dlp';
        }

        $command = sprintf(
            '%s "ytsearch5:%s" --dump-json --no-playlist 2>/dev/null',
            escapeshellcmd($ytDlp),
            escapeshellarg($query),
        );

        $output = [];
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || $output === []) {
            return [];
        }

        $videos = [];
        foreach ($output as $line) {
            $json = json_decode($line, true);
            if (! is_array($json)) {
                continue;
            }

            $videos[] = [
                'id'       => is_string($json['id'] ?? null) ? $json['id'] : '',
                'title'    => is_string($json['title'] ?? null) ? $json['title'] : '',
                'duration' => (string) ($json['duration'] ?? 0),
            ];
        }

        return $videos;
    }
}
