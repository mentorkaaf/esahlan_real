<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParcelTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Document',      'description' => 'Papers, letters, envelopes',      'max_weight_kg' => 0.5,  'sort_order' => 1],
            ['name' => 'Small Package', 'description' => 'Small items, electronics, gifts',  'max_weight_kg' => 2.0,  'sort_order' => 2],
            ['name' => 'Medium Package','description' => 'Clothing, shoes, small appliances','max_weight_kg' => 10.0, 'sort_order' => 3],
            ['name' => 'Large Package', 'description' => 'Furniture parts, large items',     'max_weight_kg' => 30.0, 'sort_order' => 4],
            ['name' => 'Food Item',     'description' => 'Packaged food, groceries',         'max_weight_kg' => 5.0,  'sort_order' => 5],
            ['name' => 'Fragile',       'description' => 'Glass, ceramics, delicate items',  'max_weight_kg' => 5.0,  'sort_order' => 6],
        ];

        foreach ($types as $type) {
            DB::table('parcel_types')->insertOrIgnore(array_merge($type, [
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
