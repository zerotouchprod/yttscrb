<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_tasks', function (Blueprint $table) {
            $table->string('title', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('media_tasks', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->change();
        });
    }
};
