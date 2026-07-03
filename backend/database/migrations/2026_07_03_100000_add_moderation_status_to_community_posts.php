<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->enum('moderation_status', ['approved', 'pending', 'blocked'])
                  ->default('approved')
                  ->after('video_ready');
            $table->float('moderation_score')->default(0)->after('moderation_status');
            $table->index('moderation_status');
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropIndex(['moderation_status']);
            $table->dropColumn(['moderation_status', 'moderation_score']);
        });
    }
};
