<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->enum('app_type', ['customer', 'driver', 'vendor'])->unique();
            $table->string('min_version', 20)->default('1.0.0');   // minimum required version
            $table->string('latest_version', 20)->default('1.0.0'); // latest published version
            $table->boolean('force_update')->default(false);
            $table->text('update_message')->nullable();
            $table->string('android_url', 500)->nullable();
            $table->string('ios_url', 500)->nullable();
            $table->timestamps();
        });

        // Seed defaults
        $now = now();
        DB::table('app_versions')->insert([
            ['app_type' => 'customer', 'min_version' => '1.0.0', 'latest_version' => '1.0.0',
             'force_update' => false, 'update_message' => 'A new version is available with improvements and bug fixes.',
             'android_url' => null, 'ios_url' => null, 'created_at' => $now, 'updated_at' => $now],
            ['app_type' => 'driver',   'min_version' => '1.0.0', 'latest_version' => '1.0.0',
             'force_update' => false, 'update_message' => 'A new version is available with improvements and bug fixes.',
             'android_url' => null, 'ios_url' => null, 'created_at' => $now, 'updated_at' => $now],
            ['app_type' => 'vendor',   'min_version' => '1.0.0', 'latest_version' => '1.0.0',
             'force_update' => false, 'update_message' => 'A new version is available with improvements and bug fixes.',
             'android_url' => null, 'ios_url' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
