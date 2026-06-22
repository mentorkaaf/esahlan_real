<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add target column
        Schema::table('order_notification_templates', function (Blueprint $table) {
            $table->string('target', 20)->default('customer')->after('module_slug');
        });

        // Drop old unique and add new one with target
        Schema::table('order_notification_templates', function (Blueprint $table) {
            $table->dropUnique(['module_slug', 'status']);
            $table->unique(['module_slug', 'status', 'target']);
        });

        // Seed driver notification templates
        $driverTemplates = [
            ['status' => 'confirmed',         'title' => 'New Order Available 🔔',   'body' => 'Order #{order_number} is confirmed and ready for pickup!'],
            ['status' => 'preparing',         'title' => 'Order Being Prepared 👨‍🍳',  'body' => 'Order #{order_number} is being prepared by the vendor.'],
            ['status' => 'ready_for_pickup',  'title' => 'Ready for Pickup! 📦',     'body' => 'Order #{order_number} is ready. Head to the vendor now!'],
            ['status' => 'out_for_delivery',  'title' => 'Order Assigned 🛵',        'body' => 'Order #{order_number} has been assigned to you. Start delivery!'],
            ['status' => 'delivered',         'title' => 'Delivery Complete ✅',      'body' => 'Great job! Order #{order_number} delivered successfully.'],
            ['status' => 'cancelled',         'title' => 'Order Cancelled ❌',        'body' => 'Order #{order_number} has been cancelled by admin.'],
        ];

        foreach ($driverTemplates as $t) {
            DB::table('order_notification_templates')->insert([
                'module_slug' => null,
                'target'      => 'driver',
                'status'      => $t['status'],
                'title'       => $t['title'],
                'body'        => $t['body'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('order_notification_templates')->where('target', 'driver')->delete();
        Schema::table('order_notification_templates', function (Blueprint $table) {
            $table->dropUnique(['module_slug', 'status', 'target']);
            $table->unique(['module_slug', 'status']);
            $table->dropColumn('target');
        });
    }
};
