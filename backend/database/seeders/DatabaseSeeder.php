<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DistrictSeeder::class,
            ModuleSeeder::class,
            ModuleDepartmentSeeder::class,
            ModulePositionSeeder::class,
            ModulePermissionSeeder::class,
            ModuleRoleSeeder::class,
            SettingSeeder::class,
            AdminUserSeeder::class,
            DataProviderSeeder::class,
            LaundryItemSeeder::class,
            ParcelTypeSeeder::class,
            MovingExtraServiceSeeder::class,
            ExchangeRateSeeder::class,
            ModulePerformanceMetricSeeder::class,
        ]);
    }
}
