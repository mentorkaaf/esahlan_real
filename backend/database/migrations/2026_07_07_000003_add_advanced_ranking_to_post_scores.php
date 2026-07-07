<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('post_scores', function (Blueprint $t) {
            // Feature 1: Impression-normalized engagement rates
            if (!Schema::hasColumn('post_scores', 'like_rate')) {
                $t->float('like_rate')->default(0)->after('engagement_rate');
            }
            if (!Schema::hasColumn('post_scores', 'comment_rate')) {
                $t->float('comment_rate')->default(0)->after('like_rate');
            }
            if (!Schema::hasColumn('post_scores', 'save_rate')) {
                $t->float('save_rate')->default(0)->after('comment_rate');
            }
            if (!Schema::hasColumn('post_scores', 'share_rate')) {
                $t->float('share_rate')->default(0)->after('save_rate');
            }

            // Feature 2: Dwell time & watch behaviour
            if (!Schema::hasColumn('post_scores', 'avg_dwell_ms')) {
                // Average milliseconds users spent viewing this content (all types)
                $t->float('avg_dwell_ms')->nullable()->after('share_rate');
            }
            if (!Schema::hasColumn('post_scores', 'rewatch_count')) {
                // How many times the same user replayed the video
                $t->unsignedInteger('rewatch_count')->default(0)->after('avg_dwell_ms');
            }

            // Feature 3: Progressive distribution stage
            if (!Schema::hasColumn('post_scores', 'distribution_stage')) {
                // 0=seed(<100), 1=small(<1k), 2=medium(<10k), 3=large(<100k), 4=viral
                $t->tinyInteger('distribution_stage')->default(0)->after('rewatch_count');
                $t->index('distribution_stage');
            }
            if (!Schema::hasColumn('post_scores', 'distribution_cap')) {
                // Max impressions allowed at current stage; NULL = uncapped
                $t->unsignedInteger('distribution_cap')->default(100)->after('distribution_stage');
            }
            if (!Schema::hasColumn('post_scores', 'stage_promoted_at')) {
                $t->timestamp('stage_promoted_at')->nullable()->after('distribution_cap');
            }
        });
    }

    public function down(): void
    {
        Schema::table('post_scores', function (Blueprint $t) {
            $cols = [
                'like_rate', 'comment_rate', 'save_rate', 'share_rate',
                'avg_dwell_ms', 'rewatch_count',
                'distribution_stage', 'distribution_cap', 'stage_promoted_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('post_scores', $col)) $t->dropColumn($col);
            }
        });
    }
};
