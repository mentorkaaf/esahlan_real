<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payslip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('hr_payslips')->cascadeOnDelete();
            $table->string('label', 150);
            $table->enum('type', ['earning', 'deduction']);
            $table->string('source', 50)->default('component'); // 'base','component','attendance','commission'
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['payslip_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payslip_items');
    }
};
