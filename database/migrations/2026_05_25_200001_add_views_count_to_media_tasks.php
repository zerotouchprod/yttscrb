<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_tasks', function (Blueprint $table): void {
            $table->unsignedBigInteger('views_count')->default(0)->after('duration_sec');
            $table->index('views_count', 'idx_media_tasks_views_count');
        });
    }

    public function down(): void
    {
        Schema::table('media_tasks', function (Blueprint $table): void {
            $table->dropIndex('idx_media_tasks_views_count');
            $table->dropColumn('views_count');
        });
    }
};


