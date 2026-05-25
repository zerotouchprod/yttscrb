<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\UseCases\TranscribeVideoHandler;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\YouTubeUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

final class SeedWowContent extends Command
{
    protected $signature = 'app:seed-wow';

    protected $description = 'Fetch latest WoW videos from curated channels and dispatch transcription. 1 video per run.';

    /** @var array<int, string> YouTube channel URLs */
    private const CHANNELS = [
        'https://www.youtube.com/@BellularGaming',
        'https://www.youtube.com/channel/UCAbaiKvP8kZfY706loT4ivg',
        'https://www.youtube.com/@Hazelnuttygames',
        'https://www.youtube.com/@TaliesinEvitel',
        'https://www.youtube.com/@SignsOfKelani',
        'https://www.youtube.com/@MrGM',
    ];

    /** @var int Minimum video duration in seconds (2 min — excludes Shorts) */
    private const MIN_DURATION_SEC = 120;

    /** @var int Maximum video duration in seconds (30 min) */
    private const MAX_DURATION_SEC = 1800;

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
                $videoId = $video['id'] ?? '';
                $title = $video['title'] ?? 'Untitled';
                $duration = (int) ($video['duration'] ?? 0);

                if ($videoId === '') {
                    continue;
                }

                // Skip if already processed or processing
                $vid = new \App\Domain\ValueObjects\VideoId($videoId);
                if ($this->taskRepository->findCompletedByVideoId($vid) !== null) {
                    $this->line("  ∘ {$title} — already completed");
                    continue;
                }
                // Also check pending/processing via the model directly
                $existing = \App\Infrastructure\Adapters\Output\Persistence\MediaTaskModel::query()
                    ->where('video_id', $videoId)
                    ->exists();
                if ($existing) {
                    $this->line("  ∘ {$title} — already in DB (processing/pending)");
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
            $this->info('No new videos to process.');
        }

        return self::SUCCESS;
    }

    /**
     * Fetch latest 5 videos from a YouTube channel using yt-dlp.
     *
     * @return array<int, array{id: string, title: string, duration: int|null}>
     */
    private function fetchLatestVideos(string $channelUrl): array
    {
        $command = sprintf(
            'yt-dlp --flat-playlist --dump-json --playlist-items 1-5 --skip-download %s 2>/dev/null',
            escapeshellarg($channelUrl),
        );

        $output = shell_exec($command);

        if ($output === null || $output === '') {
            return [];
        }

        $videos = [];
        foreach (explode("\n", trim($output)) as $line) {
            if ($line === '') {
                continue;
            }
            $data = json_decode($line, true);
            if (! is_array($data) || ! isset($data['id'])) {
                continue;
            }
            $videos[] = [
                'id'       => $data['id'],
                'title'    => $data['title'] ?? 'Untitled',
                'duration' => $data['duration'] ?? null,
            ];
        }

        return $videos;
    }
}
