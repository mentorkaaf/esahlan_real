<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('global_support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('global_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->enum('status', ['open','in_progress','resolved','closed'])->default('open');
            $table->enum('priority', ['low','medium','high','urgent'])->default('medium');
            $table->enum('category', ['order','payment','shipping','product','refund','other'])->default('other');
            $table->foreignId('assigned_to')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('global_support_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('global_support_tickets')->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_admin')->default(false);
            $table->string('admin_name')->nullable();
            $table->timestamps();
        });

        Schema::create('global_blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->unique();
            $table->string('reason')->nullable();
            $table->timestamp('blocked_until')->nullable();
            $table->timestamps();
        });

        Schema::create('global_security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event'); // login_failed, suspicious_order, fraud_flag, etc.
            $table->string('ip_address')->nullable();
            $table->string('country')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('severity', ['low','medium','high','critical'])->default('low');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_security_logs');
        Schema::dropIfExists('global_blocked_ips');
        Schema::dropIfExists('global_support_replies');
        Schema::dropIfExists('global_support_tickets');
    }
};
