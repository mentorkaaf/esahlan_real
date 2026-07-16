<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Comments on episodes ───────────────────────────────────────────
        Schema::create('podcast_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('podcast_episodes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('podcast_comments')->onDelete('cascade');
            $table->text('body');
            $table->unsignedInteger('likes')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['episode_id', 'parent_id', 'created_at']);
        });

        // ── Playlists ──────────────────────────────────────────────────────
        Schema::create('podcast_playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->enum('privacy', ['public','private'])->default('private');
            $table->boolean('is_collaborative')->default(false);
            $table->unsignedInteger('episode_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'privacy']);
        });

        Schema::create('podcast_playlist_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained('podcast_playlists')->onDelete('cascade');
            $table->foreignId('episode_id')->constrained('podcast_episodes')->onDelete('cascade');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['playlist_id','episode_id']);
        });

        // ── Downloads ──────────────────────────────────────────────────────
        Schema::create('podcast_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('episode_id')->constrained('podcast_episodes')->onDelete('cascade');
            $table->string('local_path')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->enum('status', ['pending','downloading','completed','failed'])->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id','episode_id']);
            $table->index(['user_id','status']);
        });

        // ── Live Audio Rooms ───────────────────────────────────────────────
        Schema::create('podcast_live_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('podcast_id')->nullable()->constrained()->onDelete('set null');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->enum('status', ['scheduled','live','ended'])->default('scheduled');
            $table->unsignedInteger('listener_count')->default(0);
            $table->unsignedInteger('peak_listeners')->default(0);
            $table->enum('privacy', ['public','private','subscribers'])->default('public');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('replay_url')->nullable();
            $table->timestamps();
            $table->index(['status','scheduled_at']);
        });

        Schema::create('podcast_live_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('podcast_live_rooms')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['host','co_host','guest','listener'])->default('listener');
            $table->boolean('is_muted')->default(false);
            $table->boolean('hand_raised')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->unique(['room_id','user_id']);
        });

        // ── Bookmarks / Timestamps ─────────────────────────────────────────
        Schema::create('podcast_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('episode_id')->constrained('podcast_episodes')->onDelete('cascade');
            $table->unsignedInteger('position')->default(0); // seconds
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['user_id','episode_id']);
        });

        // ── Listen Queue ───────────────────────────────────────────────────
        Schema::create('podcast_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('episode_id')->constrained('podcast_episodes')->onDelete('cascade');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['user_id','episode_id']);
            $table->index(['user_id','position']);
        });

        // ── Comment Likes ──────────────────────────────────────────────────
        Schema::create('podcast_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('comment_id')->constrained('podcast_comments')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id','comment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_comment_likes');
        Schema::dropIfExists('podcast_queue');
        Schema::dropIfExists('podcast_bookmarks');
        Schema::dropIfExists('podcast_live_participants');
        Schema::dropIfExists('podcast_live_rooms');
        Schema::dropIfExists('podcast_downloads');
        Schema::dropIfExists('podcast_playlist_episodes');
        Schema::dropIfExists('podcast_playlists');
        Schema::dropIfExists('podcast_comments');
    }
};
