<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('community_posts', 'embedding')) {
                $table->json('embedding')->nullable();
            }
            if (!Schema::hasColumn('community_posts', 'embedding_updated_at')) {
                $table->timestamp('embedding_updated_at')->nullable();
            }
        });

        // Add index only if it doesn't already exist
        $indexes = collect(\DB::select("SHOW INDEX FROM community_posts"))->pluck('Key_name');
        if (!$indexes->contains('idx_posts_needs_embedding')) {
            Schema::table('community_posts', function (Blueprint $table) {
                $table->index(['embedding_updated_at', 'moderation_status'], 'idx_posts_needs_embedding');
            });
        }
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropIndex('idx_posts_needs_embedding');
            $table->dropColumn(['embedding', 'embedding_updated_at']);
        });
    }
};
