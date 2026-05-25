<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_task_taxonomies', function (Blueprint $table): void {
            $table->uuid('media_task_id');
            $table->uuid('taxonomy_id');
            $table->primary(['media_task_id', 'taxonomy_id']);
            $table->foreign('media_task_id')
                ->references('id')
                ->on('media_tasks')
                ->onDelete('cascade');
            $table->foreign('taxonomy_id')
                ->references('id')
                ->on('taxonomies')
                ->onDelete('cascade');
        });

        Schema::table('media_task_taxonomies', function (Blueprint $table): void {
            $table->index('taxonomy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_task_taxonomies');
    }
};
