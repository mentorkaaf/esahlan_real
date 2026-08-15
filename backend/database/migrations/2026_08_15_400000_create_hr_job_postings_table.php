<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hr_positions')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->string('location')->default('Mogadishu, Somalia');
            $table->enum('type', ['full_time', 'part_time', 'contract'])->default('full_time');
            $table->unsignedSmallInteger('openings')->default(1);
            $table->unsignedSmallInteger('hired_count')->default(0);
            $table->enum('status', ['draft', 'open', 'paused', 'closed'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('hr_job_postings'); }
};
