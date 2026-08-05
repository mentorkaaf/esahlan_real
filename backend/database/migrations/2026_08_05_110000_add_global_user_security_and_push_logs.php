<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('global_users', function (Blueprint $table) {
            $table->boolean('is_banned')->default(false)->after('fcm_token');
            $table->timestamp('banned_at')->nullable()->after('is_banned');
            $table->string('ban_reason')->nullable()->after('banned_at');
        });

        Schema::create('global_push_logs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('target')->default('all');
            $table->integer('sent_count')->default(0);
            $table->timestamps();
        });

        Schema::create('global_currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3)->unique();
            $table->decimal('rate', 12, 6)->default(1);
            $table->string('base', 3)->default('USD');
            $table->timestamp('updated_at');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_currency_rates');
        Schema::dropIfExists('global_push_logs');
        Schema::table('global_users', function (Blueprint $table) {
            $table->dropColumn(['is_banned','banned_at','ban_reason']);
        });
    }
};
