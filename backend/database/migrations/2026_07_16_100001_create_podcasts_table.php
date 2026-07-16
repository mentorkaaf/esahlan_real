<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('podcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('podcast_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('language', 10)->default('so');
            $table->string('website_url')->nullable();
            $table->string('rss_url')->nullable();
            $table->unsignedBigInteger('total_episodes')->default(0);
            $table->unsignedBigInteger('total_plays')->default(0);
            $table->unsignedBigInteger('total_followers')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->enum('privacy', ['public', 'private'])->default('public');
            $table->enum('status', ['active', 'draft', 'suspended'])->default('active');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['is_featured', 'status']);
            $table->index(['total_plays', 'status']);
            $table->index('slug');
        });
    }

    public function down(): void {
        Schema::dropIfExists('podcasts');
    }
};
