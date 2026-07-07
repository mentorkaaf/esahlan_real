<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('community_posts', 'video_ready')) {
            Schema::table('community_posts', function (Blueprint $t) {
                // Non-video posts (text, image, poll, etc.) are always visible,
                // so they default to true. video/reel posts start false and are
                // set to true by TranscodeVideoJob when the MP4 is ready.
                $t->boolean('video_ready')->default(true)->after('saves_count');
                $t->index('video_ready');
            });

            // Backfill: any existing video/reel post with no transcoding data
            // is assumed to have its original file — mark it ready so it shows
            // in the feed rather than disappearing permanently.
            \DB::statement("
                UPDATE community_posts cp
                LEFT JOIN community_post_media cpm
                    ON cpm.post_id = cp.id AND cpm.type = 'video'
                SET cp.video_ready = CASE
                    WHEN cp.type IN ('video','reel') AND cpm.transcoding_status = 'failed' THEN 0
                    WHEN cp.type IN ('video','reel') AND cpm.transcoding_status = 'ready'  THEN 1
                    WHEN cp.type IN ('video','reel') AND cpm.id IS NULL                    THEN 0
                    ELSE 1
                END
                WHERE cp.type IN ('video','reel')
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('community_posts', 'video_ready')) {
            Schema::table('community_posts', function (Blueprint $t) {
                $t->dropColumn('video_ready');
            });
        }
    }
};
