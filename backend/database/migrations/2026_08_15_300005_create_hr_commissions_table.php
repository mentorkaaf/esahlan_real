<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('period', 7);                        // "2026-08"
            $table->string('type', 50);                         // e.g. "customer_signups", "vendor_signups"
            $table->string('description', 200)->nullable();
            $table->integer('target')->default(0);              // target count/units
            $table->integer('achieved')->default(0);            // actual achieved
            $table->decimal('rate', 10, 4)->default(0);         // USD per unit OR % bonus
            $table->decimal('amount', 12, 2)->default(0);       // final commission amount
            $table->enum('status', ['pending','approved','rejected','included'])
                  ->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('payslip_id')->nullable()->constrained('hr_payslips')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'period', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_commissions');
    }
};
