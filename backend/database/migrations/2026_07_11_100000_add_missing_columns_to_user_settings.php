<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('user_settings', 'language')) {
                $table->json('language')->nullable()->after('safety');
            }
            if (!Schema::hasColumn('user_settings', 'content')) {
                $table->json('content')->nullable()->after('language');
            }
            if (!Schema::hasColumn('user_settings', 'security')) {
                $table->json('security')->nullable()->after('content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['language', 'content', 'security']);
        });
    }
};
