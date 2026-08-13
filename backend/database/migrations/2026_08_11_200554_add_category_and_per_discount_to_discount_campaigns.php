<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('discount_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('apply_to_all');
            $table->json('per_vendor_discounts')->nullable()->after('discount_value');
        });
    }
    public function down(): void {
        Schema::table('discount_campaigns', function (Blueprint $table) {
            $table->dropColumn(['category_id', 'per_vendor_discounts']);
        });
    }
};