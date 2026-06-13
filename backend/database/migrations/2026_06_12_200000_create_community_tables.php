<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('community_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('display_name')->nullable();
            $t->string('username')->unique()->nullable();
            $t->text('bio')->nullable();
            $t->string('cover_photo')->nullable();
            $t->string('website')->nullable();
            $t->boolean('is_verified')->default(false);
            $t->boolean('is_business')->default(false);
            $t->string('business_category')->nullable();
            $t->unsignedBigInteger('followers_count')->default(0);
            $t->unsignedBigInteger('following_count')->default(0);
            $t->unsignedBigInteger('posts_count')->default(0);
            $t->enum('privacy', ['public','friends','private'])->default('public');
            $t->timestamps();
        });

        Schema::create('community_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('cover_photo')->nullable();
            $t->string('avatar')->nullable();
            $t->enum('privacy', ['public','private'])->default('public');
            $t->enum('category', ['district','business','university','travel','food','general'])->default('general');
            $t->string('location')->nullable();
            $t->boolean('approval_required')->default(false);
            $t->unsignedBigInteger('members_count')->default(1);
            $t->unsignedBigInteger('posts_count')->default(0);
            $t->timestamps();
        });

        Schema::create('community_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_id')->nullable()->constrained('community_groups')->nullOnDelete();
            $t->foreignId('shared_post_id')->nullable()->constrained('community_posts')->nullOnDelete();
            $t->enum('type', ['text','image','video','reel','poll','share','service'])->default('text');
            $t->text('content')->nullable();
            $t->string('location')->nullable();
            $t->string('feeling')->nullable();
            $t->enum('privacy', ['public','followers','private'])->default('public');
            $t->boolean('is_pinned')->default(false);
            $t->boolean('comments_disabled')->default(false);
            $t->unsignedBigInteger('views_count')->default(0);
            $t->unsignedBigInteger('likes_count')->default(0);
            $t->unsignedBigInteger('comments_count')->default(0);
            $t->unsignedBigInteger('shares_count')->default(0);
            $t->unsignedBigInteger('saves_count')->default(0);
            $t->json('poll_options')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->softDeletes();
            $t->timestamps();
            $t->index(['user_id','created_at']);
            $t->index(['type','created_at']);
        });

        Schema::create('community_post_media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $t->enum('type', ['image','video']);
            $t->string('url');
            $t->string('thumbnail')->nullable();
            $t->integer('duration')->nullable();
            $t->integer('width')->nullable();
            $t->integer('height')->nullable();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('community_post_reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['like','love','wow','haha','sad','angry'])->default('like');
            $t->timestamps();
            $t->unique(['post_id','user_id']);
        });

        Schema::create('community_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('community_comments')->cascadeOnDelete();
            $t->text('content');
            $t->string('media_url')->nullable();
            $t->unsignedBigInteger('likes_count')->default(0);
            $t->unsignedBigInteger('replies_count')->default(0);
            $t->boolean('is_pinned')->default(false);
            $t->softDeletes();
            $t->timestamps();
            $t->index(['post_id','created_at']);
        });

        Schema::create('community_comment_reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('comment_id')->constrained('community_comments')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['like','love','wow','haha','sad'])->default('like');
            $t->timestamps();
            $t->unique(['comment_id','user_id']);
        });

        Schema::create('community_follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['follower_id','following_id']);
            $t->index('follower_id');
            $t->index('following_id');
        });

        Schema::create('community_stories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['image','video','text']);
            $t->string('media_url')->nullable();
            $t->string('thumbnail')->nullable();
            $t->text('text_content')->nullable();
            $t->string('bg_color')->nullable();
            $t->string('location')->nullable();
            $t->unsignedBigInteger('views_count')->default(0);
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->index(['user_id','expires_at']);
        });

        Schema::create('community_story_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_id')->constrained('community_stories')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('reaction')->nullable();
            $t->timestamps();
            $t->unique(['story_id','user_id']);
        });

        Schema::create('community_group_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained('community_groups')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role', ['owner','admin','moderator','member'])->default('member');
            $t->enum('status', ['active','pending','banned'])->default('active');
            $t->timestamps();
            $t->unique(['group_id','user_id']);
        });

        Schema::create('community_saved_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id','post_id']);
        });

        Schema::create('community_hashtags', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->unsignedBigInteger('posts_count')->default(0);
            $t->timestamps();
        });

        Schema::create('community_post_hashtags', function (Blueprint $t) {
            $t->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $t->foreignId('hashtag_id')->constrained('community_hashtags')->cascadeOnDelete();
            $t->primary(['post_id','hashtag_id']);
        });

        Schema::create('community_chats', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['direct','group'])->default('direct');
            $t->string('name')->nullable();
            $t->string('avatar')->nullable();
            $t->timestamps();
        });

        Schema::create('community_chat_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chat_id')->constrained('community_chats')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role', ['admin','member'])->default('member');
            $t->timestamp('last_read_at')->nullable();
            $t->boolean('is_muted')->default(false);
            $t->timestamps();
            $t->unique(['chat_id','user_id']);
        });

        Schema::create('community_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chat_id')->constrained('community_chats')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reply_to_id')->nullable()->constrained('community_messages')->nullOnDelete();
            $t->enum('type', ['text','image','video','audio','post_share'])->default('text');
            $t->text('content')->nullable();
            $t->string('media_url')->nullable();
            $t->integer('duration')->nullable();
            $t->boolean('is_deleted')->default(false);
            $t->softDeletes();
            $t->timestamps();
            $t->index(['chat_id','created_at']);
        });

        Schema::create('community_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $t->enum('type', ['like','comment','follow','mention','share','group_invite','story_reaction','message']);
            $t->string('notifiable_type')->nullable();
            $t->unsignedBigInteger('notifiable_id')->nullable();
            $t->text('data')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamps();
            $t->index(['user_id','is_read','created_at']);
        });

        Schema::create('community_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $t->string('reportable_type');
            $t->unsignedBigInteger('reportable_id');
            $t->enum('reason', ['spam','hate','violence','nudity','misinformation','other']);
            $t->text('description')->nullable();
            $t->enum('status', ['pending','reviewed','resolved','dismissed'])->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status','created_at']);
        });

        Schema::create('community_blocked_users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id','blocked_user_id']);
        });

        Schema::create('community_poll_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->integer('option_index');
            $t->timestamps();
            $t->unique(['post_id','user_id']);
        });
    }

    public function down(): void
    {
        $tables = [
            'community_poll_votes','community_blocked_users','community_reports',
            'community_notifications','community_messages','community_chat_members',
            'community_chats','community_post_hashtags','community_hashtags',
            'community_saved_posts','community_group_members','community_story_views',
            'community_stories','community_follows','community_comment_reactions',
            'community_comments','community_post_reactions','community_post_media',
            'community_posts','community_groups','community_profiles',
        ];
        foreach ($tables as $table) Schema::dropIfExists($table);
    }
};
