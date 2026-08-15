<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('hr_applicants')->cascadeOnDelete();
            $table->foreignId('interviewer_id')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->enum('mode', ['in_person', 'video', 'phone'])->default('in_person');
            $table->string('location_or_link')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->text('feedback')->nullable();
            $table->enum('result', ['passed', 'failed', 'on_hold'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('hr_interviews'); }
};
