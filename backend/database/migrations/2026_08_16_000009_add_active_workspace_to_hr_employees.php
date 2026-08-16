<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            // Tracks which workforce assignment is the "active workspace"
            // for employees with multiple module assignments.
            $table->foreignId('active_workspace_id')
                  ->nullable()
                  ->constrained('workforce_assignments')
                  ->nullOnDelete();

            $table->timestamp('workspace_switched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['active_workspace_id']);
            $table->dropColumn(['active_workspace_id', 'workspace_switched_at']);
        });
    }
};
