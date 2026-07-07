<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('post_scores', function (Blueprint $t) {
            if (!Schema::hasColumn('post_scores', 'velocity_24h')) {
                // Real interactions-per-hour measured from feed_interactions over 24h
                $t->float('velocity_24h')->default(0)->after('velocity_score');
            }
            if (!Schema::hasColumn('post_scores', 'watch_completion')) {
                // Average video completion rate (0.0-1.0); null for non-video posts
                $t->float('watch_completion')->nullable()->after('velocity_24h');
            }
            if (!Schema::hasColumn('post_scores', 'negative_count')) {
                // Total skip + report actions (used for quality penalty)
                $t->unsignedInteger('negative_count')->default(0)->after('watch_completion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('post_scores', function (Blueprint $t) {
            foreach (['velocity_24h', 'watch_completion', 'negative_count'] as $col) {
                if (Schema::hasColumn('post_scores', $col)) $t->dropColumn($col);
            }
        });
    }
};
