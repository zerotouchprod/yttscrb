<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cleans up old workflow data to prevent Redis and PostgreSQL bloat.
 *
 * - Deletes workflows that are completed or failed and older than N days
 * - Deletes orphaned exceptions, logs, signals, and timers
 * - Prunes failed_jobs older than N days
 *
 * Designed to run daily via Laravel scheduler.
 */
final class CleanupOldWorkflowsCommand extends Command
{
    protected $signature = 'yttscrb:cleanup-workflows
                            {--days=7 : Delete workflows older than this many days}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Remove old workflow data and failed jobs to prevent DB/Redis bloat.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        if ($this->option('dry-run')) {
            $this->info("DRY RUN — no deletions will be performed.");
        }

        $this->info("Cleaning workflows older than {$days} days (cutoff: {$cutoff->toDateTimeString()})");

        // 1. Delete completed workflows older than cutoff
        /** @var array<int> $completedIds */
        $completedIds = DB::table('workflows')
            ->where('status', 'completed')
            ->where('updated_at', '<', $cutoff)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (count($completedIds) > 0) {
            $this->deleteWorkflowBatch($completedIds, 'completed');
        }
        $completedCount = count($completedIds);

        // 2. Delete failed workflows older than cutoff
        /** @var array<int> $failedIds */
        $failedIds = DB::table('workflows')
            ->where('status', 'failed')
            ->where('updated_at', '<', $cutoff)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (count($failedIds) > 0) {
            $this->deleteWorkflowBatch($failedIds, 'failed');
        }
        $failedCount = count($failedIds);

        // 3. Delete pending/waiting workflows older than 1 day (stuck ones)
        $stuckCutoff = now()->subDay();
        /** @var array<int> $stuckIds */
        $stuckIds = DB::table('workflows')
            ->whereIn('status', ['pending', 'waiting'])
            ->where('created_at', '<', $stuckCutoff)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (count($stuckIds) > 0) {
            $this->deleteWorkflowBatch($stuckIds, 'stuck (pending/waiting > 1 day)');
        }
        $stuckCount = count($stuckIds);

        // 4. Delete orphaned exceptions (stored_workflow_id no longer exists)
        $orphanedExceptions = DB::table('workflow_exceptions')
            ->whereNotIn('stored_workflow_id', function ($query) {
                $query->select('id')->from('workflows');
            })
            ->delete();

        // 5. Delete orphaned logs
        $orphanedLogs = DB::table('workflow_logs')
            ->whereNotIn('stored_workflow_id', function ($query) {
                $query->select('id')->from('workflows');
            })
            ->delete();

        // 6. Delete orphaned signals
        $orphanedSignals = DB::table('workflow_signals')
            ->whereNotIn('stored_workflow_id', function ($query) {
                $query->select('id')->from('workflows');
            })
            ->delete();

        // 7. Delete orphaned timers
        $orphanedTimers = DB::table('workflow_timers')
            ->whereNotIn('stored_workflow_id', function ($query) {
                $query->select('id')->from('workflows');
            })
            ->delete();

        // 8. Delete orphaned relationships
        $orphanedRels = DB::table('workflow_relationships')
            ->whereNotIn('parent_workflow_id', function ($query) {
                $query->select('id')->from('workflows');
            })
            ->whereNotIn('child_workflow_id', function ($query) {
                $query->select('id')->from('workflows');
            })
            ->delete();

        // 9. Prune failed_jobs older than cutoff
        $prunedJobs = DB::table('failed_jobs')
            ->where('failed_at', '<', $cutoff)
            ->delete();

        $this->info("Cleaned up:");
        $this->info("  Completed workflows: {$completedCount}");
        $this->info("  Failed workflows: {$failedCount}");
        $this->info("  Stuck workflows: {$stuckCount}");
        $this->info("  Orphaned exceptions: {$orphanedExceptions}");
        $this->info("  Orphaned logs: {$orphanedLogs}");
        $this->info("  Orphaned signals: {$orphanedSignals}");
        $this->info("  Orphaned timers: {$orphanedTimers}");
        $this->info("  Orphaned relationships: {$orphanedRels}");
        $this->info("  Pruned failed_jobs: {$prunedJobs}");

        $remaining = DB::table('workflows')->count();
        $this->info("Remaining workflows: {$remaining}");

        return self::SUCCESS;
    }

    /**
     * @param array<int> $ids
     */
    private function deleteWorkflowBatch(array $ids, string $label): void
    {
        if ($this->option('dry-run')) {
            $this->line("  [DRY RUN] Would delete " . count($ids) . " {$label} workflows");
            return;
        }

        // Delete in FK-safe order
        foreach (array_chunk($ids, 200) as $chunk) {
            DB::table('workflow_signals')->whereIn('stored_workflow_id', $chunk)->delete();
            DB::table('workflow_timers')->whereIn('stored_workflow_id', $chunk)->delete();
            DB::table('workflow_exceptions')->whereIn('stored_workflow_id', $chunk)->delete();
            DB::table('workflow_logs')->whereIn('stored_workflow_id', $chunk)->delete();
            DB::table('workflow_relationships')->where(function ($q) use ($chunk) {
                $q->whereIn('parent_workflow_id', $chunk)
                  ->orWhereIn('child_workflow_id', $chunk);
            })->delete();
            DB::table('workflows')->whereIn('id', $chunk)->delete();
        }

        $this->line("  Deleted " . count($ids) . " {$label} workflows");
    }
}
