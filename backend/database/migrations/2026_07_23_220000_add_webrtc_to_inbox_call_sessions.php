<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbox_call_sessions', function (Blueprint $table) {
            $table->text('offer_sdp')->nullable()->after('room_name');
            $table->text('answer_sdp')->nullable()->after('offer_sdp');
            $table->json('user_ice_candidates')->nullable()->after('answer_sdp');
            $table->json('admin_ice_candidates')->nullable()->after('user_ice_candidates');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_call_sessions', function (Blueprint $table) {
            $table->dropColumn(['offer_sdp', 'answer_sdp', 'user_ice_candidates', 'admin_ice_candidates']);
        });
    }
};
