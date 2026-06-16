<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('module_slug', 50)->nullable()->index(); // null = global default
            $table->string('status', 50);
            $table->string('title', 255);
            $table->text('body');
            $table->timestamps();
            $table->unique(['module_slug', 'status']);
        });

        // Seed global defaults
        $statuses = [
            ['status' => 'pending',          'title' => 'Order Received! 🎉',    'body' => 'Your order #{order_number} has been placed successfully.'],
            ['status' => 'confirmed',         'title' => 'Order Confirmed ✅',    'body' => 'Vendor confirmed your order #{order_number}. Preparing soon!'],
            ['status' => 'preparing',         'title' => 'Being Prepared 👨‍🍳',    'body' => 'Your order #{order_number} is being prepared.'],
            ['status' => 'ready_for_pickup',  'title' => 'Order Ready 📦',        'body' => 'Your order #{order_number} is ready for pickup.'],
            ['status' => 'out_for_delivery',  'title' => 'On the Way! 🛵',        'body' => 'Your order #{order_number} is on the way. Driver is heading to you!'],
            ['status' => 'delivered',         'title' => 'Delivered! 🏠',         'body' => 'Your order #{order_number} has been delivered. Enjoy!'],
            ['status' => 'cancelled',         'title' => 'Order Cancelled ❌',    'body' => 'Your order #{order_number} has been cancelled.'],
            ['status' => 'refunded',          'title' => 'Order Refunded 💰',     'body' => 'Your order #{order_number} has been refunded.'],
            ['status' => 'failed',            'title' => 'Order Failed ❌',       'body' => 'Your order #{order_number} could not be completed.'],
        ];

        foreach ($statuses as $s) {
            DB::table('order_notification_templates')->insert([
                'module_slug' => null,
                'status'      => $s['status'],
                'title'       => $s['title'],
                'body'        => $s['body'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notification_templates');
    }
};
