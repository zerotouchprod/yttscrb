<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // pg_trgm is PostgreSQL-only; skip on SQLite (e.g. testing).
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // GIN index on title for ILIKE '%query%' — uses pg_trgm for trigram-aware fast search.
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_media_tasks_title_trgm ON media_tasks USING GIN (title gin_trgm_ops)',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // pg_trgm is PostgreSQL-only; skip on SQLite (e.g. testing).
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_media_tasks_title_trgm');
    }
};
