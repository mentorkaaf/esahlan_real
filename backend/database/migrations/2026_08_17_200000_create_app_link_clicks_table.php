<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_link_clicks', function (Blueprint $table) {
            $table->id();
            $table->enum('device_type', ['android', 'ios', 'desktop'])->default('desktop');
            $table->string('ip', 45)->nullable();
            $table->string('country_code', 3)->nullable();
            $table->string('country_name', 100)->nullable();
            $table->string('browser', 80)->nullable();
            $table->string('os', 80)->nullable();
            $table->string('referer', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_link_clicks');
    }
};
