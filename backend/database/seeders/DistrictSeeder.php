<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $districts = [
            ['name' => 'Abdiaziz',      'name_so' => 'Abdiaziz',      'latitude' => 2.0800, 'longitude' => 45.3500],
            ['name' => 'Howlwadaag',    'name_so' => 'Howlwadaag',    'latitude' => 2.0700, 'longitude' => 45.3400],
            ['name' => 'Waaberi',       'name_so' => 'Waaberi',       'latitude' => 2.0500, 'longitude' => 45.3300],
            ['name' => 'Hamarweyne',    'name_so' => 'Hamarweyne',    'latitude' => 2.0400, 'longitude' => 45.3250],
            ['name' => 'Hamarjajab',    'name_so' => 'Hamarjajab',    'latitude' => 2.0350, 'longitude' => 45.3200],
            ['name' => 'Warta Nabadda', 'name_so' => 'Warta Nabadda', 'latitude' => 2.0600, 'longitude' => 45.3600],
            ['name' => 'Deyniile',      'name_so' => 'Deyniile',      'latitude' => 2.1200, 'longitude' => 45.3100],
            ['name' => 'Dharkeynley',   'name_so' => 'Dharkeynley',   'latitude' => 2.1000, 'longitude' => 45.3200],
            ['name' => 'Wadajir',       'name_so' => 'Wadajir',       'latitude' => 2.0550, 'longitude' => 45.3000],
            ['name' => 'Hiliwaa',       'name_so' => 'Hiliwaa',       'latitude' => 2.0450, 'longitude' => 45.2900],
            ['name' => 'Hodan',         'name_so' => 'Hodan',         'latitude' => 2.0400, 'longitude' => 45.3100],
            ['name' => 'Kaaraan',       'name_so' => 'Kaaraan',       'latitude' => 2.0650, 'longitude' => 45.2800],
            ['name' => 'Daarusalaam',   'name_so' => 'Daarusalaam',   'latitude' => 2.0750, 'longitude' => 45.2700],
            ['name' => 'Kahda',         'name_so' => 'Kahda',         'latitude' => 2.0200, 'longitude' => 45.2600],
            ['name' => 'Garasbaaley',   'name_so' => 'Garasbaaley',   'latitude' => 2.1100, 'longitude' => 45.2500],
            ['name' => 'Shibis',        'name_so' => 'Shibis',        'latitude' => 2.0500, 'longitude' => 45.3400],
            ['name' => 'Shangaani',     'name_so' => 'Shangaani',     'latitude' => 2.0300, 'longitude' => 45.3350],
            ['name' => 'Gubadleey',     'name_so' => 'Gubadleey',     'latitude' => 2.1300, 'longitude' => 45.3800],
            ['name' => 'Yaqshiid',      'name_so' => 'Yaqshiid',      'latitude' => 2.0900, 'longitude' => 45.3600],
            ['name' => 'Boondheere',    'name_so' => 'Boondheere',    'latitude' => 2.0250, 'longitude' => 45.3450],
        ];

        foreach ($districts as $district) {
            DB::table('districts')->updateOrInsert(
                ['name' => $district['name']],
                [
                    'name'       => $district['name'],
                    'name_so'    => $district['name_so'],
                    'latitude'   => $district['latitude'],
                    'longitude'  => $district['longitude'],
                    'slug'       => Str::slug($district['name']),
                    'status'     => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
