<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->string('pin', 6)->nullable()->comment('4-6 digit PIN fallback')->after('password');
            $table->timestamp('login_at')->nullable()->after('pin');
            $table->string('last_login_ip', 45)->nullable()->after('login_at');
            $table->string('remember_token', 100)->nullable()->after('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn(['password', 'pin', 'login_at', 'last_login_ip', 'remember_token']);
        });
    }
};
