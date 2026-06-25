<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('community_ads', function (Blueprint $t) {
            if (!Schema::hasColumn('community_ads', 'target_country')) {
                $t->string('target_country')->nullable()->after('target_gender');
                $t->string('target_city')->nullable()->after('target_country');
                $t->integer('target_min_age')->nullable()->after('target_city');
                $t->integer('target_max_age')->nullable()->after('target_min_age');
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_ads', function (Blueprint $t) {
            $t->dropColumn(['target_country', 'target_city', 'target_min_age', 'target_max_age']);
        });
    }
};
