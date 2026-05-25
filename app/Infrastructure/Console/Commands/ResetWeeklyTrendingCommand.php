<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Application\Ports\Output\ViewTrackerInterface;
use Illuminate\Console\Command;

/**
 * Resets the weekly trending Redis sorted set every Monday at 00:00 UTC.
 * After deletion, the set rebuilds organically from new page views.
 */
final class ResetWeeklyTrendingCommand extends Command
{
    protected $signature = 'trending:reset';

    protected $description = 'Reset the weekly trending sorted set in Redis (run every Monday at 00:00 UTC).';

    public function __construct(
        private readonly ViewTrackerInterface $viewTracker,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->viewTracker->resetWeeklyData();

        $this->info('Weekly trending data reset successfully.');

        return Command::SUCCESS;
    }
}
