<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_campaigns', function (Blueprint $table) {
            $table->boolean('notif_paused')->default(false)->after('is_active');
            $table->string('notif_title', 255)->nullable()->after('notif_paused');
            $table->text('notif_body')->nullable()->after('notif_title');
            $table->unsignedTinyInteger('notif_interval_hours')->default(2)->after('notif_body');
        });
    }

    public function down(): void
    {
        Schema::table('discount_campaigns', function (Blueprint $table) {
            $table->dropColumn(['notif_paused','notif_title','notif_body','notif_interval_hours']);
        });
    }
};
