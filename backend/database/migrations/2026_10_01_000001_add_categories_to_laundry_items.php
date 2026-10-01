<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('laundry_items', function (Blueprint $table) {
            $table->string('main_category')->default('Clean & Press')->after('sort_order');
            $table->string('sub_category')->nullable()->after('main_category');
        });
    }

    public function down(): void
    {
        Schema::table('laundry_items', function (Blueprint $table) {
            $table->dropColumn(['main_category', 'sub_category']);
        });
    }
};
