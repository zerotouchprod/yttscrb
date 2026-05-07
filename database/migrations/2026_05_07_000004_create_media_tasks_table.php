<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('youtube_url');
            $table->string('video_id', 20)->nullable();
            $table->string('title')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('workflow_id', 100)->nullable();
            $table->text('result_text')->nullable();
            $table->text('summary')->nullable();
            $table->integer('duration_sec')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });

        // Dedup: one completed task per video_id (anonymous mode)
        DB::statement(
            "CREATE UNIQUE INDEX idx_media_tasks_video_completed ON media_tasks (video_id) WHERE status = 'completed'");
    }

    public function down(): void
    {
        Schema::dropIfExists('media_tasks');
    }
};
