<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('wallet_pin')->nullable()->after('password');
        });

        // Copy existing password hashes to wallet_pin so current users keep working
        DB::table('users')->whereNotNull('password')->update([
            'wallet_pin' => DB::raw('password'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wallet_pin');
        });
    }
};
