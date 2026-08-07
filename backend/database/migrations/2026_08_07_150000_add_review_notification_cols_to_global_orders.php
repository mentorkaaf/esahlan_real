<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('global_orders', function (Blueprint $table) {
            $table->timestamp('review_notified_at')->nullable()->after('delivered_at');
            $table->unsignedTinyInteger('review_reminder_count')->default(0)->after('review_notified_at');
        });
    }
    public function down(): void {
        Schema::table('global_orders', function (Blueprint $table) {
            $table->dropColumn(['review_notified_at', 'review_reminder_count']);
        });
    }
};
