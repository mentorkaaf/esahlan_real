<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->text('logo_url')->nullable()->change();
            $table->text('cta_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->string('logo_url', 500)->nullable()->change();
            $table->string('cta_url', 500)->nullable()->change();
        });
    }
};
