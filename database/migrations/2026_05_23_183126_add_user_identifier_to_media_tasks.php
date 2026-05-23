<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_tasks', function (Blueprint $table) {
            $table->string('user_identifier', 64)->nullable()->after('user_id');
            $table->index('user_identifier');
        });
    }

    public function down(): void
    {
        Schema::table('media_tasks', function (Blueprint $table) {
            $table->dropIndex(['user_identifier']);
            $table->dropColumn('user_identifier');
        });
    }
};
