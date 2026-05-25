<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomies', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('type', 20);
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->integer('video_count')->default(0);
            $table->timestampsTz();

            $table->unique(['type', 'slug']);
            $table->index('type');
            $table->index('video_count');
        });

        DB::statement('ALTER TABLE taxonomies ADD CONSTRAINT taxonomies_type_check CHECK (type IN (\'topic\', \'speaker\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomies');
    }
};
