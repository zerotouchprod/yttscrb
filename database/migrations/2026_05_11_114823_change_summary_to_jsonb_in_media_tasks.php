<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Backfill — wrap legacy plain-text rows in valid JSON before changing column type.
        // Chunked to avoid table-lock on large datasets.
        DB::table('media_tasks')
            ->whereNotNull('summary')
            ->where('summary', 'not like', '{%')
            ->orderBy('id')
            ->chunk(100, function ($tasks): void {
                foreach ($tasks as $task) {
                    DB::table('media_tasks')
                        ->where('id', $task->id)
                        ->update([
                            'summary' => json_encode([
                                'introduction' => $task->summary,
                                'key_points'   => [],
                                'conclusion'   => null,
                            ]),
                        ]);
                }
            });

        // Step 2: Change column type TEXT → JSON.
        // PostgreSQL requires an explicit USING clause for text→json cast;
        // Schema Builder omits it, so we use raw DDL here.
        DB::statement('ALTER TABLE media_tasks ALTER COLUMN summary TYPE json USING summary::json');
    }

    public function down(): void
    {
        // Convert structured JSON back to plain-text (introduction only) before reverting column type.
        DB::table('media_tasks')
            ->whereNotNull('summary')
            ->orderBy('id')
            ->chunk(100, function ($tasks): void {
                foreach ($tasks as $task) {
                    $decoded = json_decode($task->summary, true);
                    $text = is_array($decoded) ? ($decoded['introduction'] ?? '') : '';
                    DB::table('media_tasks')
                        ->where('id', $task->id)
                        ->update(['summary' => $text]);
                }
            });

        DB::statement('ALTER TABLE media_tasks ALTER COLUMN summary TYPE text USING summary::text');
    }
};
