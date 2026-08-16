<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('type', 50);         // leave_approved, leave_rejected, payslip_paid, announcement, order_status, general
            $table->string('title', 200);
            $table->string('body', 500)->nullable();
            $table->json('data')->nullable();   // extra context (leave_id, payslip_id, etc.)
            $table->string('icon', 60)->nullable();   // Font Awesome class
            $table->string('url', 300)->nullable();   // click-through URL
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'read_at']);
            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_notifications');
    }
};
