<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Live room moderation settings
        Schema::create('live_room_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_room_id')->unique()->constrained('live_rooms')->cascadeOnDelete();
            $table->boolean('slow_mode')->default(false);
            $table->unsignedInteger('slow_mode_seconds')->default(30);
            $table->boolean('followers_only')->default(false);
            $table->unsignedInteger('min_follow_seconds')->default(0);
            $table->boolean('subscribers_only')->default(false);
            $table->json('blocked_words')->nullable();
            $table->boolean('comments_disabled')->default(false);
            $table->timestamps();
        });

        // Chat-muted users per room (host can mute from chat)
        Schema::create('live_chat_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('muted_until')->nullable();  // null = permanent for session
            $table->timestamps();

            $table->unique(['live_room_id', 'user_id']);
        });

        // Pinned chat messages
        Schema::create('live_chat_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('live_room_messages')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('live_room_id');  // one pinned message per room at a time
        });

        // Likes per room (per user, no duplicates)
        Schema::create('live_room_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['live_room_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_room_likes');
        Schema::dropIfExists('live_chat_pins');
        Schema::dropIfExists('live_chat_mutes');
        Schema::dropIfExists('live_room_settings');
    }
};
