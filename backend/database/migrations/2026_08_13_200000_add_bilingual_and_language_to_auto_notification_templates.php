<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_notification_templates', function (Blueprint $table) {
            $table->string('title_so')->nullable()->after('body_template');
            $table->text('body_so')->nullable()->after('title_so');
            $table->enum('language', ['en', 'so', 'both'])->default('en')->after('body_so');
            $table->string('send_time', 10)->nullable()->after('language');
            $table->string('target_audience', 50)->default('all')->after('send_time');
        });
    }

    public function down(): void
    {
        Schema::table('auto_notification_templates', function (Blueprint $table) {
            $table->dropColumn(['title_so', 'body_so', 'language', 'send_time', 'target_audience']);
        });
    }
};
