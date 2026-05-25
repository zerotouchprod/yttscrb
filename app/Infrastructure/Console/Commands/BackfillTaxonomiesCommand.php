<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\TaxonomyRepositoryInterface;
use App\Domain\ValueObjects\TaxonomyType;
use Illuminate\Console\Command;

final class BackfillTaxonomiesCommand extends Command
{
    protected $signature = 'taxonomy:backfill {--dry-run} {--limit=500}';

    protected $description = 'Backfill taxonomy data for completed transcriptions. Uses existing summary JSONB — no AI calls.';

    public function __construct(
        private readonly MediaTaskRepositoryInterface $taskRepository,
        private readonly TaxonomyRepositoryInterface $taxonomyRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $tasks = $this->taskRepository->findCompletedWithoutTaxonomies($limit);

        if (empty($tasks)) {
            $this->info('No tasks to backfill.');

            return Command::SUCCESS;
        }

        $this->info(sprintf('Found %d tasks to backfill.', count($tasks)));
        $processed = 0;

        foreach ($tasks as $task) {
            // Speaker taxonomy — from channel_name
            $channelName = $task->channelName();
            if ($channelName !== null && $channelName !== '') {
                if (! $dryRun) {
                    $taxonomy = $this->taxonomyRepository->findOrCreate(TaxonomyType::Speaker, $channelName);
                    $this->taxonomyRepository->attachToTask($task->id(), $taxonomy);
                }
                $this->line("  Speaker: {$channelName}");
            }

            // Topic taxonomies — from AI output stored in summary JSONB
            $summary = $task->summary();
            if ($summary !== null) {
                foreach ($summary->topics() as $topicName) {
                    if (! $dryRun) {
                        $taxonomy = $this->taxonomyRepository->findOrCreate(TaxonomyType::Topic, $topicName);
                        $this->taxonomyRepository->attachToTask($task->id(), $taxonomy);
                    }
                    $this->line("  Topic: {$topicName}");
                }
            }

            $processed++;
        }

        $label = $dryRun ? '[DRY RUN] Would process' : 'Processed';
        $this->info("{$label} {$processed} tasks.");

        return Command::SUCCESS;
    }
}
