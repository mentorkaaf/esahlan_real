<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add price offer to recommendations (optional — agent may include a price)
        Schema::table('request_recommendations', function (Blueprint $table) {
            $table->decimal('offered_price', 10, 2)->nullable()->after('message');
            $table->decimal('counter_price', 10, 2)->nullable()->after('offered_price');
            $table->text('counter_message')->nullable()->after('counter_price');
        });

        // request_offer_status: extend status enum to include countered
        DB::statement("ALTER TABLE request_recommendations MODIFY COLUMN status ENUM('pending','accepted','rejected','countered') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('request_recommendations', function (Blueprint $table) {
            $table->dropColumn(['offered_price', 'counter_price', 'counter_message']);
        });
        DB::statement("ALTER TABLE request_recommendations MODIFY COLUMN status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending'");
    }
};
