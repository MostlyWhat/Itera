<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Task made from a weekly task keeps its template and its date. The
     * unique index is the date key from the plan: one Task for each template
     * and date, even when two requests run at the same time (NFR-4). A
     * deleted template keeps its Tasks, so their history stays.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('recurrence_template_id')->nullable()->after('project_id')
                ->constrained()->nullOnDelete();
            $table->date('recurrence_date')->nullable()->after('recurrence_template_id');

            $table->unique(['recurrence_template_id', 'recurrence_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['recurrence_template_id', 'recurrence_date']);
            $table->dropConstrainedForeignId('recurrence_template_id');
            $table->dropColumn('recurrence_date');
        });
    }
};
