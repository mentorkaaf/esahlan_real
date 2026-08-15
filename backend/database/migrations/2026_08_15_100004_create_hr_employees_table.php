<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no', 20)->unique(); // ESH-EMP-0001
            $table->unsignedBigInteger('user_id')->nullable(); // link to app users
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hr_positions')->nullOnDelete();

            // Personal
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('dob')->nullable();
            $table->string('national_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('district')->nullable();
            $table->text('address')->nullable();
            $table->string('photo')->nullable();

            // Employment
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time');
            $table->enum('status', ['active', 'probation', 'suspended', 'terminated', 'resigned'])->default('active');
            $table->date('hire_date')->nullable();
            $table->date('probation_end')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->string('bank_account')->nullable();
            $table->string('mobile_money_number')->nullable();
            $table->json('emergency_contact')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
            $table->index('employment_type');
            $table->index('department_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employees');
    }
};
