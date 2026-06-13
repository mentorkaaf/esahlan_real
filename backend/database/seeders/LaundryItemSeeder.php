<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LaundryItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'T-Shirt',        'name_so' => 'Shaati',        'normal_price' => 1.50, 'express_price' => 3.00, 'normal_days' => 3, 'express_hours' => 6],
            ['name' => 'Shirt (Dress)',   'name_so' => 'Shaati Rasmi',  'normal_price' => 2.00, 'express_price' => 4.00, 'normal_days' => 3, 'express_hours' => 6],
            ['name' => 'Trousers',        'name_so' => 'Surwaal',       'normal_price' => 2.50, 'express_price' => 5.00, 'normal_days' => 3, 'express_hours' => 6],
            ['name' => 'Suit (Full)',     'name_so' => 'Suut',          'normal_price' => 8.00, 'express_price' => 15.00,'normal_days' => 3, 'express_hours' => 12],
            ['name' => 'Jilbab',         'name_so' => 'Jilbaab',       'normal_price' => 3.00, 'express_price' => 6.00, 'normal_days' => 3, 'express_hours' => 6],
            ['name' => 'Abaya',          'name_so' => 'Abaaya',        'normal_price' => 3.50, 'express_price' => 7.00, 'normal_days' => 3, 'express_hours' => 6],
            ['name' => 'Blanket (Single)','name_so' => 'Baandho Yar',  'normal_price' => 5.00, 'express_price' => 10.00,'normal_days' => 4, 'express_hours' => 24],
            ['name' => 'Blanket (Double)','name_so' => 'Baandho Weyn', 'normal_price' => 8.00, 'express_price' => 16.00,'normal_days' => 4, 'express_hours' => 24],
            ['name' => 'Bedsheet',       'name_so' => 'Sharaaxad',     'normal_price' => 4.00, 'express_price' => 8.00, 'normal_days' => 3, 'express_hours' => 12],
            ['name' => 'Jacket',         'name_so' => 'Jaakad',        'normal_price' => 4.00, 'express_price' => 8.00, 'normal_days' => 3, 'express_hours' => 12],
            ['name' => 'Curtain (1 pair)','name_so' => 'Shaashad',     'normal_price' => 10.00,'express_price' => 20.00,'normal_days' => 5, 'express_hours' => 24],
            ['name' => 'Underwear/Socks','name_so' => 'Hidhmo',        'normal_price' => 0.75, 'express_price' => 1.50, 'normal_days' => 2, 'express_hours' => 4],
        ];

        foreach ($items as $i => $item) {
            DB::table('laundry_items')->insertOrIgnore(array_merge($item, [
                'is_active'  => true,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
