<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add agent_user_id to properties so agents own their listings
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_user_id')->nullable()->after('vendor_id');
            $table->foreign('agent_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // Add rent_agent role
        DB::table('roles')->insertOrIgnore([
            'name'        => 'Rent Agent',
            'slug'        => 'rent_agent',
            'description' => 'Property rental agent — lists and manages properties',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['agent_user_id']);
            $table->dropColumn('agent_user_id');
        });
        DB::table('roles')->where('slug', 'rent_agent')->delete();
    }
};
