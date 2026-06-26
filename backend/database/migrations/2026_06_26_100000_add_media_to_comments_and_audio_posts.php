<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add media to comments
        if (!Schema::hasColumn('community_comments', 'media_url')) {
            Schema::table('community_comments', function (Blueprint $t) {
                $t->string('media_url')->nullable()->after('content');
                $t->string('media_type')->nullable()->after('media_url');
            });
        }

        // Expand post types to include audio and document
        DB::statement("ALTER TABLE community_posts MODIFY type ENUM('text','image','video','reel','poll','share','service','audio','document') DEFAULT 'text'");
    }

    public function down(): void
    {
        Schema::table('community_comments', function (Blueprint $t) {
            if (Schema::hasColumn('community_comments', 'media_url')) $t->dropColumn(['media_url', 'media_type']);
        });
    }
};
