<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('hr_payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();

            // Snapshot of employment at run time
            $table->decimal('base_salary', 12, 2);
            $table->integer('working_days')->default(26);       // standard working days in period
            $table->integer('present_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('leave_days')->default(0);

            // Computed totals
            $table->decimal('gross_earnings', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('attendance_deduction', 12, 2)->default(0);  // absent_days × daily_rate
            $table->decimal('commission_total', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);

            // Payment tracking
            $table->enum('payment_status', ['unpaid','paid'])->default('unpaid');
            $table->string('payment_method', 50)->nullable();   // e.g. "EVC Plus", "Bank Transfer"
            $table->string('payment_ref', 100)->nullable();
            $table->timestamp('paid_at')->nullable();

            // PDF storage
            $table->string('pdf_path')->nullable();

            $table->timestamps();

            $table->unique(['run_id', 'employee_id']);
            $table->index(['employee_id', 'run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payslips');
    }
};
