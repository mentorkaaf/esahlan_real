<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Add not_interested to feed_interactions enum
        DB::statement("ALTER TABLE feed_interactions MODIFY COLUMN type ENUM('view','like','comment','share','save','watch','click','skip','not_interested')");

        // 2. User similarity table for collaborative filtering
        Schema::create('user_similarities', function (Blueprint $table) {
            $table->unsignedBigInteger('user_a')->index();
            $table->unsignedBigInteger('user_b')->index();
            $table->float('score')->default(0);
            $table->primary(['user_a', 'user_b']);
            $table->timestamp('computed_at')->nullable();
        });

        // 3. Store onboarding interests selection
        Schema::table('user_interests', function (Blueprint $table) {
            if (!Schema::hasColumn('user_interests', 'from_onboarding')) {
                $table->boolean('from_onboarding')->default(false)->after('score');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_similarities');
        DB::statement("ALTER TABLE feed_interactions MODIFY COLUMN type ENUM('view','like','comment','share','save','watch','click','skip')");
    }
};
