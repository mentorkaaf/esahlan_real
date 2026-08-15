<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('hr_performance_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->json('scores')->nullable();            // {goal_id: score}
            $table->json('competency_scores')->nullable(); // {communication: 4, teamwork: 3, ...}
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unique(['cycle_id', 'employee_id']);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('hr_reviews'); }
};
