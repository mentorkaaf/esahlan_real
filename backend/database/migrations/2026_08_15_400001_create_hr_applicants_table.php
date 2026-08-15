<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('hr_job_postings')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('cv_path')->nullable();
            $table->enum('stage', ['applied','screening','interview','offer','hired','rejected'])->default('applied');
            $table->timestamp('stage_changed_at')->nullable();
            $table->unsignedTinyInteger('rating')->nullable()->comment('1-5');
            $table->text('notes')->nullable();
            $table->string('source')->nullable()->comment('linkedin,referral,careers_page,etc');
            $table->boolean('is_hired')->default(false);
            $table->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('hr_applicants'); }
};
