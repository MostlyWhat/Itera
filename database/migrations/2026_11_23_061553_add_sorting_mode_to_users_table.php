<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The way the user's Tasks are sorted (ITERA-08): "stack", "deadline",
     * or "balanced". It sits next to `week_start`, the other planning
     * setting of the user.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sorting_mode', 20)->default('stack')->after('week_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sorting_mode');
        });
    }
};
