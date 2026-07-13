<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60);                    // e.g. login.failed, token.revoked, role.changed
            $table->string('severity', 8)->default('info'); // crit | warn | info | ok
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_identifier', 100)->nullable(); // phone or email (for pre-auth events)
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();           // arbitrary context (e.g. role, module, resource_id)
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['event', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_logs');
    }
};
