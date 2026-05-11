<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite index for the public library query on the main page:
     * WHERE status='completed' AND title IS NOT NULL AND dmca_removed_at IS NULL
     * ORDER BY created_at DESC
     *
     * The composite index covers the most selective combination — completed + not removed.
     * A partial index (CREATE INDEX ... WHERE status='completed' AND dmca_removed_at IS NULL)
     * would be more efficient for PostgreSQL, but would break MySQL compatibility.
     * title IS NOT NULL is not indexable in a standard B-tree, but the query planner
     * can filter the remaining rows cheaply after the index scan.
     */
    public function up(): void
    {
        // Composite index is PostgreSQL/MySQL only; skip on SQLite (testing).
        $driver = DB::getDriverName();
        if ($driver !== 'pgsql' && $driver !== 'mysql') {
            return;
        }

        Schema::table('media_tasks', function (Blueprint $table) {
            $table->index(
                ['status', 'dmca_removed_at', 'created_at'],
                'idx_public_library',
            );
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'pgsql' && $driver !== 'mysql') {
            return;
        }

        Schema::table('media_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_public_library');
        });
    }
};
