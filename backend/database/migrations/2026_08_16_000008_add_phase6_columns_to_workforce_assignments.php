<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workforce_assignments', function (Blueprint $table) {
            // Assignment type — what kind of assignment this is
            $table->enum('assignment_type', [
                'primary',
                'secondary',
                'temporary',
                'acting',
                'project_based',
            ])->default('primary')->after('role_in_module');

            // Access level — what system access the employee gets
            $table->enum('access_level', [
                'read_only',
                'standard',
                'elevated',
                'admin',
            ])->default('standard')->after('assignment_type');

            // Planned operational start date (separate from assigned_at which is record creation)
            $table->date('start_date')->nullable()->after('access_level');

            // Planned end date (temporary/project_based assignments)
            $table->date('planned_end_date')->nullable()->after('start_date');

            // Who the employee reports to within this module assignment
            $table->foreignId('reporting_manager_id')
                ->nullable()
                ->after('planned_end_date')
                ->constrained('hr_employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workforce_assignments', function (Blueprint $table) {
            $table->dropForeign(['reporting_manager_id']);
            $table->dropColumn([
                'assignment_type',
                'access_level',
                'start_date',
                'planned_end_date',
                'reporting_manager_id',
            ]);
        });
    }
};
