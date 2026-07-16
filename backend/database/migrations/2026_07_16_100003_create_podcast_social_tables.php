<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Podcast follows (subscribe to a show)
        Schema::create('podcast_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('podcast_id')->constrained()->onDelete('cascade');
            $table->boolean('notify_new_episodes')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'podcast_id']);
            $table->index('podcast_id');
        });

        // Episode play progress (Continue Listening)
        Schema::create('podcast_episode_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('episode_id')->constrained('podcast_episodes')->onDelete('cascade');
            $table->unsignedInteger('position')->default(0); // seconds - resume point
            $table->boolean('completed')->default(false);
            $table->unsignedSmallInteger('play_count')->default(1);
            $table->timestamp('last_played_at')->useCurrent();
            $table->timestamps();
            $table->unique(['user_id', 'episode_id']);
            $table->index(['user_id', 'last_played_at']);
            $table->index(['user_id', 'completed']);
        });

        // Episode likes
        Schema::create('podcast_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('episode_id')->constrained('podcast_episodes')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id', 'episode_id']);
            $table->index('episode_id');
        });

        // Episode saves / bookmarks
        Schema::create('podcast_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('episode_id')->constrained('podcast_episodes')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id', 'episode_id']);
            $table->index(['user_id', 'created_at']);
        });

        // Podcast ratings
        Schema::create('podcast_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('podcast_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('review')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'podcast_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('podcast_ratings');
        Schema::dropIfExists('podcast_saves');
        Schema::dropIfExists('podcast_likes');
        Schema::dropIfExists('podcast_episode_plays');
        Schema::dropIfExists('podcast_follows');
    }
};
