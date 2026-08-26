<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('deliverymen', function (Blueprint $table) {
            if (!Schema::hasColumn('deliverymen', 'accepted_orders_count'))
                $table->unsignedInteger('accepted_orders_count')->default(0)->after('total_deliveries');
            if (!Schema::hasColumn('deliverymen', 'rejected_orders_count'))
                $table->unsignedInteger('rejected_orders_count')->default(0)->after('accepted_orders_count');
            if (!Schema::hasColumn('deliverymen', 'completed_orders_count'))
                $table->unsignedInteger('completed_orders_count')->default(0)->after('rejected_orders_count');
        });
    }
    public function down(): void {}
};
