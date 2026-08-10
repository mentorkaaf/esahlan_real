<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_pay_accounts', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('icon'); // path: mobile_pay_logos/xxx.png
        });
    }

    public function down(): void
    {
        Schema::table('mobile_pay_accounts', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
