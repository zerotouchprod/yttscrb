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
            $table->string('channel_name', 255)->nullable();
            $table->string('channel_slug', 255)->nullable();
            $table->index('channel_slug');
        });
    }

    public function down(): void
    {
        Schema::table('media_tasks', function (Blueprint $table): void {
            $table->dropIndex(['channel_slug']);
            $table->dropColumn(['channel_name', 'channel_slug']);
        });
    }
};
