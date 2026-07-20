<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->string('category')->default('normal')->after('animation');
            // normal | premium | epic | legendary | seasonal | exclusive | vip
            $table->string('rarity')->default('normal')->after('category');
            $table->string('sound_effect')->nullable()->after('rarity');
            $table->string('icon_url')->nullable()->after('sound_effect');
            $table->string('animation_url')->nullable()->after('icon_url');
            $table->unsignedInteger('display_priority')->default(0)->after('sort');
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->timestamp('scheduled_start')->nullable()->after('is_featured');
            $table->timestamp('scheduled_end')->nullable()->after('scheduled_start');
            // coins host earns per send (may differ from buyer cost)
            $table->unsignedInteger('host_coins')->default(0)->after('coins');
        });
    }

    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->dropColumn([
                'category', 'rarity', 'sound_effect', 'icon_url', 'animation_url',
                'display_priority', 'is_featured', 'scheduled_start', 'scheduled_end',
                'host_coins',
            ]);
        });
    }
};
