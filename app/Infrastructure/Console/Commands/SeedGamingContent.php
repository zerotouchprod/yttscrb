<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\UseCases\TranscribeVideoHandler;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\YouTubeUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class SeedGamingContent extends Command
{
    protected $signature = 'app:seed-gaming';

    protected $description = 'Fetch latest gaming videos from curated channels and dispatch transcription. 1 video per run.';

    /** @var array<int, string> YouTube channel URLs */
    private const CHANNELS = [
        'https://www.youtube.com/@IGN',
        'https://www.youtube.com/@Gamespot',
        'https://www.youtube.com/@GameRanx',
        'https://www.youtube.com/@DigitalFoundry',
        'https://www.youtube.com/@SkillUp',
        'https://www.youtube.com/@ACG-Game-Reviews',
        'https://www.youtube.com/@videogamedunkey',
        'https://www.youtube.com/@videogamedunkey-dunk',
        'https://www.youtube.com/@Strat-Edgy',
        'https://www.youtube.com/@SummoningSalt',
    ];

    /** @var int Minimum video duration in seconds (2 min — excludes Shorts) */
    private const MIN_DURATION_SEC = 120;

    /** @var int Maximum video duration in seconds (300 min) */
    private const MAX_DURATION_SEC = 18000;

    public function __construct(
        private readonly MediaTaskRepositoryInterface $taskRepository,
        private readonly TranscribeVideoHandler $transcribeHandler,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $found = false;

        foreach (self::CHANNELS as $channel) {
            $videos = $this->fetchLatestVideos($channel);
            $this->line("Channel {$channel}: " . count($videos) . ' videos fetched');

            foreach ($videos as $video) {
                $videoId = $video['id'];
                $title = $video['title'];
                $duration = (int) $video['duration'];

                if ($videoId === '') {
                    continue;
                }

                // Skip if already completed successfully
                $vid = new \App\Domain\ValueObjects\VideoId($videoId);
                if ($this->taskRepository->findCompletedByVideoId($vid) !== null) {
                    $this->line("  ∘ {$title} — already completed");
                    continue;
                }

                // Skip if previous attempt failed with a permanent (non-retryable) error
                if ($this->hasPermanentFailure($videoId)) {
                    $this->line("  ∘ {$title} — previously failed (permanent error)");
                    continue;
                }

                if ($duration < self::MIN_DURATION_SEC) {
                    $this->line("  ∘ {$title} — too short ({$duration}s, likely a Short)");
                    continue;
                }

                if ($duration > self::MAX_DURATION_SEC) {
                    $this->line("  ∘ {$title} — too long ({$duration}s)");
                    continue;
                }

                // Dispatch!
                $url = new YouTubeUrl("https://youtube.com/watch?v={$videoId}");
                $task = MediaTask::create((string) Str::uuid(), $url);
                $this->transcribeHandler->handle($task);

                $this->info("  ✓ Dispatched: {$title} ({$duration}s)");
                $found = true;
                break; // 1 video per channel loop
            }

            if ($found) {
                break; // 1 video total per command run
            }
        }

        if (! $found) {
            $this->info('No new gaming videos to process.');
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
     * @return array<int, array{id: string, title: string, duration: string}>
     */
    private function fetchLatestVideos(string $channelUrl): array
    {
        $ytDlp = config('services.yt_dlp_binary', 'yt-dlp');

        if (! is_string($ytDlp) || $ytDlp === '') {
            $ytDlp = 'yt-dlp';
        }

        $command = sprintf(
            '%s --flat-playlist --print "%%(id)s|%%(title)s|%%(duration)s" --playlist-end 3 %s/videos 2>/dev/null',
            escapeshellcmd($ytDlp),
            escapeshellarg($channelUrl),
        );

        $output = [];
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || $output === []) {
            return [];
        }

        $videos = [];
        foreach ($output as $line) {
            $parts = explode('|', $line, 3);
            if (count($parts) < 3) {
                continue;
            }

            $videos[] = [
                'id'       => trim($parts[0]),
                'title'    => trim($parts[1]),
                'duration' => trim($parts[2]),
            ];
        }

        return $videos;
    }
}
