<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Subscriptions ─────────────────────────────────────────────────────
        Schema::create('live_subscription_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('tier');           // basic / supporter / superfan
            $table->decimal('price_usd', 8, 2);
            $table->string('badge_emoji')->default('⭐');
            $table->string('badge_label')->default('Subscriber');
            $table->json('perks')->nullable(); // ["Ad-free", "Exclusive badge"]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['host_id', 'tier']);
        });

        Schema::create('live_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('tier')->default('basic');
            $table->decimal('price_usd', 8, 2)->default(4.99);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['subscriber_id', 'host_id']);
        });

        // ── Live Goals ────────────────────────────────────────────────────────
        Schema::create('live_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->string('type')->default('coins'); // coins / gifts / likes / followers
            $table->string('title');
            $table->integer('target');
            $table->integer('current')->default(0);
            $table->string('status')->default('active'); // active / completed / cancelled
            $table->timestamps();
        });

        // ── Q&A Questions ─────────────────────────────────────────────────────
        Schema::create('live_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('username');
            $table->string('avatar')->default('');
            $table->text('question');
            $table->string('status')->default('pending'); // pending / active / answered / dismissed
            $table->timestamps();
        });

        // ── VOD Recordings ────────────────────────────────────────────────────
        Schema::create('live_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('live_rooms')->cascadeOnDelete();
            $table->string('recording_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->integer('view_count')->default(0);
            $table->string('egress_id')->nullable(); // LiveKit egress ID
            $table->string('status')->default('processing'); // processing / ready / failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_recordings');
        Schema::dropIfExists('live_questions');
        Schema::dropIfExists('live_goals');
        Schema::dropIfExists('live_subscriptions');
        Schema::dropIfExists('live_subscription_tiers');
    }
};
