<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            // JSON array of 1536 floats (text-embedding-3-small output)
            $table->json('embedding')->nullable();
            $table->timestamp('embedding_updated_at')->nullable();
        });

        // Index so we can find un-embedded posts quickly
        Schema::table('community_posts', function (Blueprint $table) {
            $table->index(['embedding_updated_at', 'status'], 'idx_posts_needs_embedding');
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropIndex('idx_posts_needs_embedding');
            $table->dropColumn(['embedding', 'embedding_updated_at']);
        });
    }
};
