<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A weekly task (ITERA-09): the title, notes, and story points that Itera
     * copies into a new Task each week on the chosen weekday. `weekday` is 0
     * for Sunday up to 6 for Saturday. The team comes from the Project.
     */
    public function up(): void
    {
        Schema::create('recurrence_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('weekday');
            $table->unsignedTinyInteger('story_points')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurrence_templates');
    }
};
