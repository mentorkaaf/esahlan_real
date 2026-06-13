<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EsahlanHealthCheck extends Command
{
    protected $signature = 'esahlan:health';
    protected $description = 'Check eSahlan system health — database, tables, seeders';

    public function handle(): void
    {
        $this->info('');
        $this->info('  ███████╗███████╗ █████╗ ██╗  ██╗██╗      █████╗ ███╗   ██╗');
        $this->info('  ██╔════╝██╔════╝██╔══██╗██║  ██║██║     ██╔══██╗████╗  ██║');
        $this->info('  █████╗  ███████╗███████║███████║██║     ███████║██╔██╗ ██║');
        $this->info('  ██╔══╝  ╚════██║██╔══██║██╔══██║██║     ██╔══██║██║╚██╗██║');
        $this->info('  ███████╗███████║██║  ██║██║  ██║███████╗██║  ██║██║ ╚████║');
        $this->info('  ╚══════╝╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝╚═╝  ╚═══╝');
        $this->info('');
        $this->info('  🚀 eSahlan Super App — Health Check');
        $this->info('  ────────────────────────────────────');

        // Database connection
        try {
            DB::connection()->getPdo();
            $this->info('  ✅ Database: Connected');
        } catch (\Exception $e) {
            $this->error('  ❌ Database: ' . $e->getMessage());
            return;
        }

        // Check tables
        $requiredTables = [
            'roles', 'permissions', 'users', 'districts', 'modules',
            'vendors', 'products', 'categories', 'orders', 'order_items',
            'wallets', 'transactions', 'withdrawal_requests', 'commissions',
            'banners', 'loyalty_points', 'referrals', 'notifications',
            'conversations', 'messages', 'reviews', 'carts', 'cart_items',
            'settings', 'data_providers', 'data_packages', 'exchange_rates',
            'laundry_items', 'parcel_types', 'deliverymen', 'audit_logs',
        ];

        $this->info('');
        $this->info('  📊 Database Tables:');
        $allGood = true;
        foreach ($requiredTables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $count = DB::table($table)->count();
                $this->line("  ✅ {$table} ({$count} records)");
            } else {
                $this->error("  ❌ {$table} — TABLE MISSING! Run: php artisan migrate");
                $allGood = false;
            }
        }

        // Check seeders
        $this->info('');
        $this->info('  🌱 Seeder Status:');
        $checks = [
            'roles'          => ['table' => 'roles',           'min' => 10, 'label' => 'Roles (10 expected)'],
            'districts'      => ['table' => 'districts',       'min' => 20, 'label' => 'Districts (20 expected)'],
            'modules'        => ['table' => 'modules',         'min' => 12, 'label' => 'Modules (12 expected)'],
            'settings'       => ['table' => 'settings',        'min' => 30, 'label' => 'Settings (30+ expected)'],
            'admin'          => ['table' => 'users',           'min' => 1,  'label' => 'Admin User'],
            'data_providers' => ['table' => 'data_providers',  'min' => 4,  'label' => 'Data Providers (4 expected)'],
            'laundry_items'  => ['table' => 'laundry_items',   'min' => 10, 'label' => 'Laundry Items'],
            'exchange_rates' => ['table' => 'exchange_rates',  'min' => 8,  'label' => 'Exchange Rates'],
        ];

        foreach ($checks as $check) {
            if (!DB::getSchemaBuilder()->hasTable($check['table'])) continue;
            $count = DB::table($check['table'])->count();
            if ($count >= $check['min']) {
                $this->line("  ✅ {$check['label']} — {$count} records");
            } else {
                $this->warn("  ⚠️  {$check['label']} — only {$count} records (expected {$check['min']}+)");
            }
        }

        // Admin user
        $this->info('');
        $admin = DB::table('users')->where('email', 'admin@esahlan.com')->first();
        if ($admin) {
            $this->info('  👤 Admin Login:');
            $this->line('     Email:    admin@esahlan.com');
            $this->line('     Password: Admin@eSahlan2024!');
            $this->line('     Panel:    http://localhost:8000/admin');
        } else {
            $this->warn('  ⚠️  Admin user not found. Run: php artisan db:seed --class=AdminUserSeeder');
        }

        $this->info('');
        $this->info('  🌐 API Base URL: http://localhost:8000/api/v1/');
        $this->info('  📋 Postman Collection: eSahlan_API.postman_collection.json');
        $this->info('');

        if ($allGood) {
            $this->info('  🎉 All checks passed! Backend is ready for testing.');
        } else {
            $this->error('  ⚠️  Some issues found. Fix them before testing.');
        }

        $this->info('');
    }
}
