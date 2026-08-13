<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = [];
        foreach (['feed_seen_posts', 'feed_interactions', 'community_posts', 'community_post_hashtags'] as $tbl) {
            $rows = DB::select('SHOW INDEX FROM ' . $tbl);
            foreach ($rows as $row) {
                $existing[$row->Key_name] = true;
            }
        }
        if (empty($existing['fsp_user_seen_at'])) {
            Schema::table('feed_seen_posts', function (Blueprint $table) { $table->index(['user_id','seen_at'],'fsp_user_seen_at'); });
        }
        if (empty($existing['fsp_user_post'])) {
            Schema::table('feed_seen_posts', function (Blueprint $table) { $table->index(['user_id','post_id'],'fsp_user_post'); });
        }
        if (empty($existing['fi_user_created'])) {
            Schema::table('feed_interactions', function (Blueprint $table) { $table->index(['user_id','created_at'],'fi_user_created'); });
        }
        if (empty($existing['fi_post_type'])) {
            Schema::table('feed_interactions', function (Blueprint $table) { $table->index(['post_id','type'],'fi_post_type'); });
        }
        if (empty($existing['cp_user_created'])) {
            Schema::table('community_posts', function (Blueprint $table) { $table->index(['user_id','created_at'],'cp_user_created'); });
        }
        if (empty($existing['cp_created_eng'])) {
            Schema::table('community_posts', function (Blueprint $table) { $table->index(['created_at','likes_count'],'cp_created_eng'); });
        }
        if (empty($existing['cph_post_id'])) {
            Schema::table('community_post_hashtags', function (Blueprint $table) { $table->index(['post_id'],'cph_post_id'); });
        }
    }
    public function down(): void {}
};
