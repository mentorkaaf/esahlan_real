<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin',       'slug' => 'super_admin',        'description' => 'Full system access'],
            ['name' => 'Admin',             'slug' => 'admin',              'description' => 'General admin access'],
            ['name' => 'Operations Manager','slug' => 'operations_manager', 'description' => 'Manage day-to-day operations'],
            ['name' => 'Finance Manager',   'slug' => 'finance_manager',    'description' => 'Manage finance and payouts'],
            ['name' => 'Marketing Manager', 'slug' => 'marketing_manager',  'description' => 'Manage banners and campaigns'],
            ['name' => 'Customer Support',  'slug' => 'customer_support',   'description' => 'Handle customer queries'],
            ['name' => 'Vendor Owner',      'slug' => 'vendor_owner',       'description' => 'Own and manage a vendor store'],
            ['name' => 'Vendor Employee',   'slug' => 'vendor_employee',    'description' => 'Work in a vendor store'],
            ['name' => 'Deliveryman',       'slug' => 'deliveryman',        'description' => 'Deliver orders'],
            ['name' => 'Customer',          'slug' => 'customer',           'description' => 'Regular app user'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                ['name' => $role['name'], 'slug' => $role['slug'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
