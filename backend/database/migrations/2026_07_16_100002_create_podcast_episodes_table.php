<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('podcast_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('show_notes')->nullable();
            $table->string('audio_url');
            $table->string('hls_url')->nullable();
            $table->string('cover_image')->nullable();
            $table->unsignedInteger('duration')->default(0);      // seconds
            $table->unsignedBigInteger('file_size')->default(0);  // bytes
            $table->unsignedInteger('bitrate')->nullable();       // kbps
            $table->unsignedBigInteger('play_count')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('comment_count')->default(0);
            $table->unsignedBigInteger('download_count')->default(0);
            $table->unsignedBigInteger('share_count')->default(0);
            $table->unsignedSmallInteger('season')->default(1);
            $table->unsignedSmallInteger('episode_number')->default(1);
            $table->enum('episode_type', ['full', 'trailer', 'bonus'])->default('full');
            $table->boolean('is_explicit')->default(false);
            $table->enum('status', ['draft', 'published', 'scheduled', 'private'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('allow_downloads')->default(true);
            $table->boolean('allow_comments')->default(true);
            $table->json('chapters')->nullable();
            $table->text('transcript')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['podcast_id', 'status', 'published_at']);
            $table->index(['user_id', 'status']);
            $table->index(['play_count', 'status']);
            $table->index('slug');
        });
    }

    public function down(): void {
        Schema::dropIfExists('podcast_episodes');
    }
};
