<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workforce_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                  ->constrained('hr_employees')
                  ->cascadeOnDelete();

            $table->foreignId('module_id')
                  ->constrained('modules')
                  ->cascadeOnDelete();

            // Optional role within that module (e.g. supervisor, agent, operator, staff)
            $table->string('role_in_module', 60)->nullable();

            // active | suspended | ended
            $table->string('status', 20)->default('active')->index();

            $table->timestamp('assigned_at');

            // hr_staff.id — who made the assignment
            $table->unsignedBigInteger('assigned_by');

            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // One active assignment per employee per module — enforced in service layer
            // (no DB unique so historical reassignment is possible)
            $table->index(['employee_id', 'module_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_assignments');
    }
};
