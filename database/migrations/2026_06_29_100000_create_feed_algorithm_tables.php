<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tracks every meaningful user interaction for feed personalization
        Schema::create('feed_interactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $t->enum('type', ['view','like','comment','share','save','watch','click','skip']);
            $t->integer('duration_ms')->nullable(); // watch time for videos
            $t->float('scroll_depth', 3, 2)->nullable(); // 0.0–1.0 how far they scrolled past
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
            $t->index(['post_id', 'type']);
            $t->index(['user_id', 'type', 'created_at']);
        });

        // Tracks user interest categories derived from behavior
        Schema::create('user_interests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('category'); // hashtag, post_type, creator_id, content_keyword
            $t->string('value');    // the actual interest value
            $t->float('score', 8, 4)->default(0); // decaying interest score
            $t->timestamp('last_interaction_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'category', 'value']);
            $t->index(['user_id', 'score']);
        });

        // Tracks which posts a user has already seen (for deduplication)
        Schema::create('feed_seen_posts', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $t->timestamp('seen_at')->useCurrent();
            $t->primary(['user_id', 'post_id']);
            $t->index(['user_id', 'seen_at']);
        });

        // Pre-computed post quality scores (updated periodically)
        Schema::create('post_scores', function (Blueprint $t) {
            $t->foreignId('post_id')->primary()->constrained('community_posts')->cascadeOnDelete();
            $t->float('engagement_score', 10, 4)->default(0);
            $t->float('velocity_score', 10, 4)->default(0);  // engagement rate over time
            $t->float('quality_score', 10, 4)->default(0);    // content quality signals
            $t->float('viral_score', 10, 4)->default(0);      // viral potential
            $t->float('final_score', 10, 4)->default(0);      // combined score
            $t->unsignedInteger('impression_count')->default(0);
            $t->unsignedInteger('engaged_count')->default(0);  // users who interacted
            $t->float('engagement_rate', 5, 4)->default(0);   // engaged/impressions
            $t->timestamp('last_scored_at')->nullable();
            $t->timestamps();
            $t->index('final_score');
            $t->index('viral_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_scores');
        Schema::dropIfExists('feed_seen_posts');
        Schema::dropIfExists('user_interests');
        Schema::dropIfExists('feed_interactions');
    }
};
