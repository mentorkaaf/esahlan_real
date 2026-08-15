<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();              // "2026-08" format
            $table->string('label', 100);                       // "August 2026 Payroll"
            $table->enum('status', ['draft','pending_approval','approved','paid'])
                  ->default('draft');

            // Totals (computed on generate, recomputed on approve)
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->integer('employee_count')->default(0);

            // Workflow tracking
            $table->foreignId('generated_by')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejection_note')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_runs');
    }
};
