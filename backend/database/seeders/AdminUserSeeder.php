<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = DB::table('roles')->where('slug', 'super_admin')->first();

        if (!$superAdminRole) {
            $this->command->error('Run RoleSeeder first!');
            return;
        }

        // Remove existing admin if any
        DB::table('users')->where('email', 'admin@esahlan.com')->delete();

        $userId = DB::table('users')->insertGetId([
            'uuid'              => (string)Str::uuid(),
            'name'              => 'Super Admin',
            'email'             => 'admin@esahlan.com',
            'phone'             => '+252617000001',
            'password'          => Hash::make('Admin@eSahlan2024!'),
            'role_id'           => $superAdminRole->id,
            'status'            => 'active',
            'referral_code'     => 'ESADMIN',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'preferred_language'=> 'en',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Create wallet for admin
        DB::table('wallets')->insert([
            'owner_type' => 'App\\Models\\User',
            'owner_id'   => $userId,
            'balance'    => 0,
            'currency'   => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Admin user created: admin@esahlan.com / Admin@eSahlan2024!');
    }
}
