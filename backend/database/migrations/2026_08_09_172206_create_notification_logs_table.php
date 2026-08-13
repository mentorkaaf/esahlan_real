<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('push_notification_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_type')->default('customer'); // customer|vendor|driver
            $table->string('fcm_token', 512);
            $table->enum('status', ['sent', 'opened', 'failed'])->default('sent')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            $table->index(['push_notification_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
