<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->boolean('is_frozen')->default(false)->after('is_active');
            $table->string('frozen_reason', 500)->nullable()->after('is_frozen');
            $table->unsignedBigInteger('frozen_by')->nullable()->after('frozen_reason');
            $table->timestamp('frozen_at')->nullable()->after('frozen_by');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['is_frozen', 'frozen_reason', 'frozen_by', 'frozen_at']);
        });
    }
};
