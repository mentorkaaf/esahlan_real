<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_alert_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();           // e.g. 'new_order'
            $table->string('label');                   // Human label
            $table->string('category');                // orders | vendors | security | server | products
            $table->boolean('is_enabled')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->string('alert_key');
            $table->string('subject');
            $table->string('to_email');
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->useCurrent();
        });

        // Seed default alert settings
        $now = now();
        DB::table('admin_alert_settings')->insert([
            // ── Orders ───────────────────────────────────────────────
            ['key' => 'new_order',           'label' => 'New Order Placed',              'category' => 'orders',   'is_enabled' => true, 'description' => 'Email when any new order is placed by a customer.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'order_cancelled',     'label' => 'Order Cancelled',               'category' => 'orders',   'is_enabled' => true, 'description' => 'Email when a customer or vendor cancels an order.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'order_refund',        'label' => 'Refund Requested',              'category' => 'orders',   'is_enabled' => true, 'description' => 'Email when a refund is requested on an order.', 'created_at' => $now, 'updated_at' => $now],
            // ── Vendors ──────────────────────────────────────────────
            ['key' => 'new_vendor',          'label' => 'New Vendor Registration',       'category' => 'vendors',  'is_enabled' => true, 'description' => 'Email when a new vendor registers and awaits approval.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'vendor_approved',     'label' => 'Vendor Approved',               'category' => 'vendors',  'is_enabled' => false, 'description' => 'Email when admin approves a vendor.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'new_product',         'label' => 'New Product Added by Vendor',   'category' => 'vendors',  'is_enabled' => true, 'description' => 'Email when a vendor adds a new product/food item.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'low_stock',           'label' => 'Low Stock Alert',               'category' => 'vendors',  'is_enabled' => true, 'description' => 'Email when a product stock falls below 5 units.', 'created_at' => $now, 'updated_at' => $now],
            // ── Agents / Users ────────────────────────────────────────
            ['key' => 'new_agent',           'label' => 'New Agent Registration',        'category' => 'users',    'is_enabled' => true, 'description' => 'Email when a new delivery agent/driver registers.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'new_customer',        'label' => 'New Customer Sign-up',          'category' => 'users',    'is_enabled' => false, 'description' => 'Email when a new customer registers (high volume — keep off for busy apps).', 'created_at' => $now, 'updated_at' => $now],
            // ── Security ─────────────────────────────────────────────
            ['key' => 'failed_login_spike',  'label' => 'Failed Login Spike (Brute Force)', 'category' => 'security', 'is_enabled' => true, 'description' => 'Email when >20 failed admin logins occur within 10 minutes.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'admin_login',         'label' => 'Admin Login (New IP)',          'category' => 'security', 'is_enabled' => true, 'description' => 'Email when admin panel is accessed from a new IP address.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'suspicious_activity', 'label' => 'Suspicious Activity Detected',  'category' => 'security', 'is_enabled' => true, 'description' => 'Email when multiple security events occur in a short window.', 'created_at' => $now, 'updated_at' => $now],
            // ── Server ───────────────────────────────────────────────
            ['key' => 'high_cpu',            'label' => 'High CPU Usage (>85%)',         'category' => 'server',   'is_enabled' => true, 'description' => 'Email when server CPU load exceeds 85% for 3+ minutes.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'high_memory',         'label' => 'High Memory Usage (>90%)',      'category' => 'server',   'is_enabled' => true, 'description' => 'Email when RAM usage exceeds 90%.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'storage_warning',     'label' => 'Storage Warning (>80%)',        'category' => 'server',   'is_enabled' => true, 'description' => 'Email when disk usage exceeds 80% of total capacity.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'storage_critical',    'label' => 'Storage Critical (>90%)',       'category' => 'server',   'is_enabled' => true, 'description' => 'Email when disk usage exceeds 90% — urgent action required.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'queue_failed',        'label' => 'Queue Jobs Failed',             'category' => 'server',   'is_enabled' => true, 'description' => 'Email when failed queue jobs count exceeds 10.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'slow_db',             'label' => 'Slow Database Queries',         'category' => 'server',   'is_enabled' => true, 'description' => 'Email when slow DB queries exceed 50 per hour.', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_alert_logs');
        Schema::dropIfExists('admin_alert_settings');
    }
};
