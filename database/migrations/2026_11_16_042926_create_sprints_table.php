<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A Sprint is one Monday-to-Sunday week of a team. A team has at most one
     * Sprint for each week. `completed_points` and `review_completed_at` are
     * set by the sprint review (ITERA-10), so later edits of Tasks do not
     * change old results.
     */
    public function up(): void
    {
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->date('week_end');
            $table->string('goal', 200)->nullable();
            $table->unsignedSmallInteger('target_points')->nullable();
            $table->string('status')->default('open');
            $table->unsignedSmallInteger('completed_points')->nullable();
            $table->timestamp('review_completed_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'week_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprints');
    }
};
