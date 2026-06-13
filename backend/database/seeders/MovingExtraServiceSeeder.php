<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MovingExtraServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Packing Service',     'name_so' => 'Adeegga Xirashada',  'price' => 25.00, 'unit' => 'flat',  'sort_order' => 1],
            ['name' => 'Unpacking Service',   'name_so' => 'Furista Xidmada',    'price' => 20.00, 'unit' => 'flat',  'sort_order' => 2],
            ['name' => 'Extra Mover (1 person)','name_so' => 'Kici Dheeraad ah', 'price' => 15.00, 'unit' => 'per_hour','sort_order' => 3],
            ['name' => 'Furniture Disassembly','name_so' => 'Kala-dejinta Dharka','price' => 30.00,'unit' => 'flat',  'sort_order' => 4],
            ['name' => 'Furniture Assembly', 'name_so' => 'Iskudhafka Dharka',   'price' => 30.00, 'unit' => 'flat',  'sort_order' => 5],
            ['name' => 'Storage (1 day)',     'name_so' => 'Kaydinta Alaabta',   'price' => 10.00, 'unit' => 'per_day','sort_order' => 6],
            ['name' => 'Piano/Heavy Item',    'name_so' => 'Alaab Culus',        'price' => 50.00, 'unit' => 'flat',  'sort_order' => 7],
        ];

        foreach ($services as $service) {
            DB::table('moving_extra_services')->insertOrIgnore(array_merge($service, [
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
