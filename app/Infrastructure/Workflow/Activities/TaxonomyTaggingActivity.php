<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\TaxonomyRepositoryInterface;
use App\Domain\ValueObjects\TaxonomyType;
use Illuminate\Container\Container;
use Workflow\Activity;

final class TaxonomyTaggingActivity extends Activity
{
    /** @var int */
    public $tries = 3;

    /** @var array<int, int> Exponential backoff: 1s, 5s, 10s */
    public $backoff = [1, 5, 10];

    public function execute(string $taskId): void
    {
        /** @var MediaTaskRepositoryInterface $taskRepository */
        $taskRepository = Container::getInstance()->make(MediaTaskRepositoryInterface::class);
        /** @var TaxonomyRepositoryInterface $taxonomyRepository */
        $taxonomyRepository = Container::getInstance()->make(TaxonomyRepositoryInterface::class);

        $task = $taskRepository->findByIdOrFail($taskId);

        // Speaker taxonomy — from channel_name stored during audio download
        $channelName = $task->channelName();
        if ($channelName !== null && $channelName !== '') {
            $taxonomy = $taxonomyRepository->findOrCreate(TaxonomyType::Speaker, $channelName);
            $taxonomyRepository->attachToTask($taskId, $taxonomy);
        }

        // Topic taxonomies — from AI output stored in summary JSONB
        $summary = $task->summary();
        if ($summary !== null) {
            foreach ($summary->topics() as $topicName) {
                $taxonomy = $taxonomyRepository->findOrCreate(TaxonomyType::Topic, $topicName);
                $taxonomyRepository->attachToTask($taskId, $taxonomy);
            }
        }
    }
}
