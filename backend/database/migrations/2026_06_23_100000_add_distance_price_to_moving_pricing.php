<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('moving_pricing', function (Blueprint $table) {
            $table->decimal('distance_price', 10, 2)->default(20)->after('price_per_room');
        });
        // Set default $20 for all existing routes
        DB::table('moving_pricing')->update(['distance_price' => 20]);
    }
    public function down(): void
    {
        Schema::table('moving_pricing', function (Blueprint $table) {
            $table->dropColumn('distance_price');
        });
    }
};
