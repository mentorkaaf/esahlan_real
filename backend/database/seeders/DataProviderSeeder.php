<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['name' => 'Hormuud Telecom', 'slug' => 'hormuud', 'logo' => null, 'is_active' => true],
            ['name' => 'Somtel',          'slug' => 'somtel',  'logo' => null, 'is_active' => true],
            ['name' => 'Somnet',          'slug' => 'somnet',  'logo' => null, 'is_active' => true],
            ['name' => 'Amtel',           'slug' => 'amtel',   'logo' => null, 'is_active' => true],
        ];

        $packages = [
            'hormuud' => [
                ['name' => '100MB – 1 Day',    'data_mb' => 100,   'validity_days' => 1,  'price' => 0.50],
                ['name' => '300MB – 3 Days',   'data_mb' => 300,   'validity_days' => 3,  'price' => 1.00],
                ['name' => '1GB – 7 Days',     'data_mb' => 1024,  'validity_days' => 7,  'price' => 2.00],
                ['name' => '3GB – 14 Days',    'data_mb' => 3072,  'validity_days' => 14, 'price' => 5.00],
                ['name' => '5GB – 30 Days',    'data_mb' => 5120,  'validity_days' => 30, 'price' => 8.00],
                ['name' => '10GB – 30 Days',   'data_mb' => 10240, 'validity_days' => 30, 'price' => 15.00],
                ['name' => '20GB – 30 Days',   'data_mb' => 20480, 'validity_days' => 30, 'price' => 25.00],
                ['name' => 'Unlimited – 30D',  'data_mb' => 999999,'validity_days' => 30, 'price' => 40.00],
            ],
            'somtel' => [
                ['name' => '500MB – 7 Days',   'data_mb' => 512,   'validity_days' => 7,  'price' => 1.50],
                ['name' => '2GB – 15 Days',    'data_mb' => 2048,  'validity_days' => 15, 'price' => 4.00],
                ['name' => '5GB – 30 Days',    'data_mb' => 5120,  'validity_days' => 30, 'price' => 8.00],
                ['name' => '15GB – 30 Days',   'data_mb' => 15360, 'validity_days' => 30, 'price' => 20.00],
            ],
            'somnet' => [
                ['name' => '1GB – 7 Days',     'data_mb' => 1024,  'validity_days' => 7,  'price' => 2.50],
                ['name' => '5GB – 30 Days',    'data_mb' => 5120,  'validity_days' => 30, 'price' => 10.00],
                ['name' => '10GB – 30 Days',   'data_mb' => 10240, 'validity_days' => 30, 'price' => 18.00],
            ],
            'amtel' => [
                ['name' => '200MB – 3 Days',   'data_mb' => 200,   'validity_days' => 3,  'price' => 0.75],
                ['name' => '1GB – 7 Days',     'data_mb' => 1024,  'validity_days' => 7,  'price' => 2.00],
                ['name' => '4GB – 30 Days',    'data_mb' => 4096,  'validity_days' => 30, 'price' => 7.00],
            ],
        ];

        foreach ($providers as $provider) {
            $id = DB::table('data_providers')->insertGetId([
                'name'       => $provider['name'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($packages[$provider['slug']] as $pkg) {
                $dataMb = $pkg['data_mb'];
                $dataAmount = $dataMb >= 999999 ? 'Unlimited' : ($dataMb >= 1024 ? ($dataMb/1024).'GB' : $dataMb.'MB');
                DB::table('data_packages')->insert([
                    'provider_id'  => $id,
                    'name'         => $pkg['name'],
                    'data_amount'  => $dataAmount,
                    'validity_days'=> $pkg['validity_days'],
                    'price'        => $pkg['price'],
                    'is_active'    => true,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }
}
