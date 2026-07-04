<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            if (!$this->hasIndex('community_posts', 'community_posts_privacy_index')) {
                $table->index('privacy');
            }
            if (!$this->hasIndex('community_posts', 'community_posts_video_ready_index')) {
                $table->index('video_ready');
            }
        });

        Schema::table('community_comments', function (Blueprint $table) {
            if (!$this->hasIndex('community_comments', 'community_comments_user_id_index')) {
                $table->index('user_id');
            }
        });

        Schema::table('community_notifications', function (Blueprint $table) {
            if (!$this->hasIndex('community_notifications', 'community_notifications_created_at_index')) {
                $table->index('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropIndexIfExists('community_posts_privacy_index');
            $table->dropIndexIfExists('community_posts_video_ready_index');
        });
        Schema::table('community_comments', function (Blueprint $table) {
            $table->dropIndexIfExists('community_comments_user_id_index');
        });
        Schema::table('community_notifications', function (Blueprint $table) {
            $table->dropIndexIfExists('community_notifications_created_at_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($indexName);
    }
};
