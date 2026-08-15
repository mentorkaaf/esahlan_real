<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('hr_performance_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2)->default(0); // must total 100 per employee per cycle
            $table->string('target_value')->nullable();
            $table->string('achieved_value')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('hr_goals'); }
};
