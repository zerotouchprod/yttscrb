<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_tasks', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
            $table->timestamp('dmca_removed_at')->nullable()->after('failed_at');
        });

        // Backfill slugs for existing completed tasks that have a title.
        // Algorithm mirrors MediaTaskEloquentRepository::generateUniqueSlug(): base slug,
        // then UUID fragments as deterministic suffix (not video_id).
        $tasks = DB::table('media_tasks')
            ->whereNotNull('title')
            ->where('status', 'completed')
            ->whereNull('slug')
            ->select(['id', 'title'])
            ->get();

        foreach ($tasks as $task) {
            $base = Str::slug((string) $task->title);
            $slug = $base;

            if (DB::table('media_tasks')->where('slug', $slug)->exists()) {
                $suffix = strtolower(substr(str_replace('-', '', (string) $task->id), 0, 6));
                $slug = $base . '-' . $suffix;

                if (DB::table('media_tasks')->where('slug', $slug)->exists()) {
                    $suffix = strtolower(substr(str_replace('-', '', (string) $task->id), 0, 12));
                    $slug = $base . '-' . $suffix;
                }
            }

            DB::table('media_tasks')->where('id', $task->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('media_tasks', function (Blueprint $table) {
            $table->dropColumn(['slug', 'dmca_removed_at']);
        });
    }
};

