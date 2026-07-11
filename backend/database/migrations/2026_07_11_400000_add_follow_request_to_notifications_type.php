<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE community_notifications MODIFY COLUMN type ENUM(
            'like','comment','follow','follow_request','follow_request_accepted',
            'mention','share','reply','story_view','story_like','tag','remix',
            'system','ad_approved','ad_rejected','post_approved','post_rejected'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE community_notifications MODIFY COLUMN type ENUM(
            'like','comment','follow','mention','share','reply','story_view',
            'story_like','tag','remix','system','ad_approved','ad_rejected',
            'post_approved','post_rejected'
        ) NOT NULL");
    }
};
