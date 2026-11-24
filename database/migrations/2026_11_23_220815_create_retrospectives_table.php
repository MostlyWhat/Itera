<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The notes that a user writes in the review of a Sprint (ITERA-10). A
     * Sprint has at most one retrospective. Each answer is optional.
     */
    public function up(): void
    {
        Schema::create('retrospectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('went_well')->nullable();
            $table->text('blockers')->nullable();
            $table->text('improve_next')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retrospectives');
    }
};
