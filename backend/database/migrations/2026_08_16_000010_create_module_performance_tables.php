<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Metric DEFINITIONS per module — what to measure
        Schema::create('module_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('name');                  // "Orders Handled"
            $table->string('slug');                  // "orders_handled"
            $table->string('description')->nullable();
            $table->enum('unit', [
                'count',    // raw number
                'percent',  // 0–100
                'hours',
                'minutes',
                'score',    // 0–100 composite
                'rating',   // 1–5
            ])->default('count');
            $table->decimal('target_value', 10, 2)->default(100);  // target per period
            $table->unsignedTinyInteger('weight')->default(20);     // % weight in composite score (sum=100 per module)
            $table->boolean('higher_is_better')->default(true);     // false = e.g. complaint count
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['module_id', 'slug']);
        });

        // Actual MEASUREMENTS — recorded per employee per module per period
        Schema::create('employee_module_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('metric_id')->constrained('module_performance_metrics')->cascadeOnDelete();
            $table->string('period', 7);              // "2026-08" (YYYY-MM)
            $table->enum('period_type', ['monthly', 'quarterly'])->default('monthly');
            $table->decimal('actual_value', 10, 2)->default(0);
            $table->decimal('target_value', 10, 2);  // snapshot of target at record time
            $table->decimal('score', 5, 2)->nullable(); // 0–100, computed
            $table->foreignId('recorded_by')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'module_id', 'metric_id', 'period'], 'unique_emp_metric_period');
            $table->index(['module_id', 'period']);
            $table->index(['employee_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_module_metrics');
        Schema::dropIfExists('module_performance_metrics');
    }
};
