<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add denormalized employee_id / module_id columns to hr_audit_logs.
     * Allows fast queries like "all audit events for employee 42" or
     * "all access changes in eFood module" without scanning JSON.
     */
    public function up(): void
    {
        Schema::table('hr_audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->after('subject_id');
            $table->unsignedBigInteger('module_id')->nullable()->after('employee_id');
            $table->string('category', 40)->nullable()->after('action');  // 'workforce'|'payroll'|'employee'|...

            $table->index('employee_id');
            $table->index('module_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('hr_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['module_id']);
            $table->dropIndex(['category']);
            $table->dropColumn(['employee_id', 'module_id', 'category']);
        });
    }
};
