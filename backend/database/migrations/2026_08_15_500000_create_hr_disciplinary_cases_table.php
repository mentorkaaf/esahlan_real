<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_disciplinary_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('hr_staff')->restrictOnDelete();
            $table->enum('category', ['attendance','conduct','performance','other'])->default('conduct');
            $table->enum('severity',  ['minor','moderate','major'])->default('minor');
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['open','investigating','closed'])->default('open');
            $table->enum('outcome', ['verbal_warning','written_warning','suspension','termination','dismissed'])->nullable();
            $table->text('investigation_notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('hr_disciplinary_cases'); }
};
