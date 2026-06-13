<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('push_notification_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('push_notification_logs', 'image_url'))
                $table->string('image_url')->nullable()->after('body');
            if (!Schema::hasColumn('push_notification_logs', 'deep_link'))
                $table->string('deep_link')->nullable()->after('image_url');
        });
    }
    public function down(): void {
        Schema::table('push_notification_logs', function (Blueprint $table) {
            $table->dropColumn(['image_url','deep_link']);
        });
    }
};
