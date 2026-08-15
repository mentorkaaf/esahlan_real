<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('hr_disciplinary_cases')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('issued_by')->constrained('hr_staff')->restrictOnDelete();
            $table->enum('type', ['verbal','written'])->default('written');
            $table->string('title');
            $table->text('body');
            $table->string('pdf_path')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('hr_warnings'); }
};
