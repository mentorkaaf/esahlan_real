<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Support Conversations ─────────────────────────────────────────────
        Schema::create('inbox_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('agent_id')->nullable(); // assigned support agent
            $table->string('module', 50)->nullable();           // efood, crypto, epay, general …
            $table->string('subject', 200)->nullable();
            $table->enum('status', ['open', 'assigned', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_user')->default(0);    // unread for user
            $table->unsignedInteger('unread_agent')->default(0);   // unread for agent/admin
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Messages ─────────────────────────────────────────────────────────
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('sender_id');           // user_id or admin_id
            $table->enum('sender_type', ['user', 'agent', 'bot'])->default('user');
            $table->enum('type', ['text', 'image', 'audio', 'video', 'file'])->default('text');
            $table->text('content')->nullable();
            $table->string('media_url', 500)->nullable();
            $table->unsignedInteger('media_duration')->nullable(); // audio seconds
            $table->json('metadata')->nullable();               // extra data
            $table->enum('status', ['sent', 'delivered', 'seen'])->default('sent');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('inbox_conversations')->onDelete('cascade');
        });

        // ── Marketing Broadcasts ──────────────────────────────────────────────
        Schema::create('marketing_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('created_by');          // admin user id
            $table->string('title', 200);
            $table->text('body');
            $table->string('image_url', 500)->nullable();
            $table->string('video_url', 500)->nullable();
            $table->string('module', 50)->nullable();          // target module (efood, crypto…)
            $table->string('cta_label', 100)->nullable();      // "Order Now", "Buy Crypto"
            $table->string('cta_route', 200)->nullable();      // deep link route in app
            $table->enum('target', ['all', 'segment'])->default('all');
            $table->json('target_filters')->nullable();        // future: filter by region/module
            $table->enum('status', ['draft', 'sending', 'sent'])->default('draft');
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // ── Broadcast Read Tracking ───────────────────────────────────────────
        Schema::create('marketing_broadcast_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broadcast_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_read')->default(false);
            $table->boolean('cta_clicked')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->foreign('broadcast_id')->references('id')->on('marketing_broadcasts')->onDelete('cascade');
            $table->unique(['broadcast_id', 'user_id']);
        });

        // ── LiveKit Rooms for Audio Calls ─────────────────────────────────────
        Schema::create('inbox_call_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('initiated_by');
            $table->string('room_name', 100)->unique();
            $table->enum('status', ['ringing', 'active', 'ended', 'missed'])->default('ringing');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('inbox_conversations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_call_sessions');
        Schema::dropIfExists('marketing_broadcast_users');
        Schema::dropIfExists('marketing_broadcasts');
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('inbox_conversations');
    }
};
