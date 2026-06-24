<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Message reactions
        if (!Schema::hasTable('community_message_reactions')) {
            Schema::create('community_message_reactions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('message_id')->constrained('community_messages')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('emoji', 10);
                $t->timestamps();
                $t->unique(['message_id', 'user_id']);
            });
        }

        // Read receipts on messages
        if (!Schema::hasColumn('community_messages', 'read_at')) {
            Schema::table('community_messages', function (Blueprint $t) {
                $t->timestamp('read_at')->nullable()->after('is_deleted');
            });
        }

        // Story highlights
        if (!Schema::hasTable('community_story_highlights')) {
            Schema::create('community_story_highlights', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('title');
                $t->string('cover_url')->nullable();
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('community_story_highlight_items')) {
            Schema::create('community_story_highlight_items', function (Blueprint $t) {
                $t->id();
                $t->foreignId('highlight_id')->constrained('community_story_highlights')->cascadeOnDelete();
                $t->foreignId('story_id')->constrained('community_stories')->cascadeOnDelete();
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();
            });
        }

        // Mentions
        if (!Schema::hasTable('community_mentions')) {
            Schema::create('community_mentions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->morphs('mentionable'); // post or comment
                $t->foreignId('mentioned_by')->constrained('users')->cascadeOnDelete();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_mentions');
        Schema::dropIfExists('community_story_highlight_items');
        Schema::dropIfExists('community_story_highlights');
        Schema::dropIfExists('community_message_reactions');
        Schema::table('community_messages', function (Blueprint $t) {
            if (Schema::hasColumn('community_messages', 'read_at')) $t->dropColumn('read_at');
        });
    }
};
