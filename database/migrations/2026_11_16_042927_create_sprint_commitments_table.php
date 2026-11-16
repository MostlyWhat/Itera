<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A commitment puts one Task into one Sprint. It keeps a copy of the task
     * title and points, so the sprint history stays readable when the Task
     * changes or is deleted later (the task_id then becomes null).
     */
    public function up(): void
    {
        Schema::create('sprint_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('task_title');
            $table->unsignedTinyInteger('committed_points')->nullable();
            $table->string('closing_status')->nullable();
            $table->timestamps();

            $table->unique(['sprint_id', 'task_id']);
            $table->index('task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprint_commitments');
    }
};
