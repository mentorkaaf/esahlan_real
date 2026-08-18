<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EGrocerySeeder extends Seeder
{
    public function run(): void
    {
        // ── Units ─────────────────────────────────────────────────────────────
        $units = [
            ['name' => 'kg',     'abbreviation' => 'kg',  'step' => 0.5000],
            ['name' => 'g',      'abbreviation' => 'g',   'step' => 100.0000],
            ['name' => 'L',      'abbreviation' => 'L',   'step' => 0.2500],
            ['name' => 'ml',     'abbreviation' => 'ml',  'step' => 250.0000],
            ['name' => 'piece',  'abbreviation' => 'pc',  'step' => 1.0000],
            ['name' => 'pack',   'abbreviation' => 'pk',  'step' => 1.0000],
            ['name' => 'dozen',  'abbreviation' => 'dz',  'step' => 1.0000],
            ['name' => 'bag',    'abbreviation' => 'bag', 'step' => 1.0000],
            ['name' => 'carton', 'abbreviation' => 'ctn', 'step' => 1.0000],
        ];
        foreach ($units as $u) {
            DB::table('egrocery_units')->updateOrInsert(
                ['name' => $u['name']],
                array_merge($u, ['created_at' => now(), 'updated_at' => now()])
            );
        }
        $unitId = fn($name) => DB::table('egrocery_units')->where('name', $name)->value('id');

        // ── Brands ────────────────────────────────────────────────────────────
        $brands = [
            'Daallo Foods', 'Somali Fresh', 'Iftin Dairy', 'Barwaaqo',
            'Hanan Mills', 'Al Noor', 'Candheeye', 'Generic',
        ];
        foreach ($brands as $b) {
            DB::table('egrocery_brands')->updateOrInsert(
                ['name' => $b],
                ['name' => $b, 'logo' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
        $brandId = fn($name) => DB::table('egrocery_brands')->where('name', $name)->value('id');

        // ── Categories (parent) ───────────────────────────────────────────────
        $parents = [
            ['name' => 'Food Staples',    'name_so' => 'Raashinka Aasaasiga', 'icon' => '🌾', 'sort_order' => 1],
            ['name' => 'Fresh Produce',   'name_so' => 'Khudaar & Miro',      'icon' => '🥦', 'sort_order' => 2],
            ['name' => 'Meat & Fish',     'name_so' => 'Hilib & Kalluun',     'icon' => '🥩', 'sort_order' => 3],
            ['name' => 'Dairy & Eggs',    'name_so' => 'Caano & Ugax',        'icon' => '🥛', 'sort_order' => 4],
            ['name' => 'Bakery',          'name_so' => 'Rootida & Doolshe',   'icon' => '🍞', 'sort_order' => 5],
            ['name' => 'Beverages',       'name_so' => 'Cabbitaanka',         'icon' => '🥤', 'sort_order' => 6],
            ['name' => 'Snacks',          'name_so' => 'Cuntooyinka Yaryar',  'icon' => '🍪', 'sort_order' => 7],
            ['name' => 'Canned & Jarred', 'name_so' => 'Kaannada & Jaraha',   'icon' => '🥫', 'sort_order' => 8],
            ['name' => 'Spices',          'name_so' => 'Xawaash & Udgoon',    'icon' => '🌶️', 'sort_order' => 9],
            ['name' => 'Household',       'name_so' => 'Guriga',              'icon' => '🏠', 'sort_order' => 10],
            ['name' => 'Personal Care',   'name_so' => 'Daryeelka Shakhsiga', 'icon' => '🧴', 'sort_order' => 11],
            ['name' => 'Baby Care',       'name_so' => 'Daryeelka Ilmaha',   'icon' => '👶', 'sort_order' => 12],
        ];

        foreach ($parents as $p) {
            $slug = Str::slug($p['name']);
            DB::table('egrocery_categories')->updateOrInsert(
                ['slug' => $slug],
                array_merge($p, [
                    'parent_id' => null, 'slug' => $slug,
                    'image' => null, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ])
            );
        }
        $catId = fn($name) => DB::table('egrocery_categories')->where('name', $name)->value('id');

        // Sub-categories
        $subs = [
            ['parent' => 'Food Staples',  'name' => 'Rice',          'name_so' => 'Bariis',        'icon' => '🍚', 'sort' => 1],
            ['parent' => 'Food Staples',  'name' => 'Flour & Pasta', 'name_so' => 'Bur & Baasto',  'icon' => '🌾', 'sort' => 2],
            ['parent' => 'Food Staples',  'name' => 'Sugar & Salt',  'name_so' => 'Sonkor & Cusbo', 'icon' => '🧂', 'sort' => 3],
            ['parent' => 'Food Staples',  'name' => 'Cooking Oil',   'name_so' => 'Saliid Karinta', 'icon' => '🫙', 'sort' => 4],
            ['parent' => 'Fresh Produce', 'name' => 'Vegetables',    'name_so' => 'Khudaar',        'icon' => '🥕', 'sort' => 1],
            ['parent' => 'Fresh Produce', 'name' => 'Fruits',        'name_so' => 'Miro',           'icon' => '🍎', 'sort' => 2],
            ['parent' => 'Beverages',     'name' => 'Water',         'name_so' => 'Biyo',           'icon' => '💧', 'sort' => 1],
            ['parent' => 'Beverages',     'name' => 'Juices',        'name_so' => 'Casiiro',        'icon' => '🧃', 'sort' => 2],
            ['parent' => 'Beverages',     'name' => 'Soft Drinks',   'name_so' => 'Cabitaan Qabow', 'icon' => '🥤', 'sort' => 3],
            ['parent' => 'Household',     'name' => 'Cleaning',      'name_so' => 'Nadiifinta',     'icon' => '🧹', 'sort' => 1],
            ['parent' => 'Household',     'name' => 'Laundry',       'name_so' => 'Dhaqista',       'icon' => '🧺', 'sort' => 2],
        ];

        foreach ($subs as $s) {
            $slug = Str::slug($s['name']);
            DB::table('egrocery_categories')->updateOrInsert(
                ['slug' => $slug],
                [
                    'parent_id'  => $catId($s['parent']),
                    'name'       => $s['name'],
                    'name_so'    => $s['name_so'],
                    'slug'       => $slug,
                    'icon'       => $s['icon'],
                    'image'      => null,
                    'sort_order' => $s['sort'],
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // ── Demo Products ─────────────────────────────────────────────────────
        $kgId     = $unitId('kg');
        $pieceId  = $unitId('piece');
        $lId      = $unitId('L');
        $bagId    = $unitId('bag');
        $packId   = $unitId('pack');

        $products = [
            // Rice
            [
                'category' => 'Rice', 'brand' => 'Barwaaqo',
                'name' => 'Basmati Rice', 'name_so' => 'Bariis Basmati',
                'slug' => 'basmati-rice', 'is_weight_based' => true,
                'base_unit' => 'kg', 'tags' => ['rice', 'staple'],
                'variants' => [
                    ['label' => '1 kg',   'unit' => 'kg', 'qty' => 1,   'price' => 1.50, 'compare' => null,  'stock' => 200, 'is_default' => false],
                    ['label' => '5 kg',   'unit' => 'kg', 'qty' => 5,   'price' => 6.50, 'compare' => 7.50,  'stock' => 100, 'is_default' => true],
                    ['label' => '25 kg',  'unit' => 'kg', 'qty' => 25,  'price' => 28.00,'compare' => 32.00, 'stock' => 40,  'is_default' => false],
                ],
            ],
            [
                'category' => 'Rice', 'brand' => 'Al Noor',
                'name' => 'Egyptian Rice', 'name_so' => 'Bariis Masar',
                'slug' => 'egyptian-rice', 'is_weight_based' => true,
                'base_unit' => 'kg', 'tags' => ['rice', 'staple'],
                'variants' => [
                    ['label' => '1 kg',  'unit' => 'kg', 'qty' => 1,  'price' => 1.20, 'compare' => null, 'stock' => 300, 'is_default' => false],
                    ['label' => '5 kg',  'unit' => 'kg', 'qty' => 5,  'price' => 5.50, 'compare' => 6.00, 'stock' => 150, 'is_default' => true],
                    ['label' => '25 kg', 'unit' => 'kg', 'qty' => 25, 'price' => 24.00,'compare' => null, 'stock' => 60,  'is_default' => false],
                ],
            ],
            // Flour
            [
                'category' => 'Flour & Pasta', 'brand' => 'Hanan Mills',
                'name' => 'Wheat Flour', 'name_so' => 'Bur Sarreen',
                'slug' => 'wheat-flour', 'is_weight_based' => true,
                'base_unit' => 'kg', 'tags' => ['flour', 'baking'],
                'variants' => [
                    ['label' => '1 kg',  'unit' => 'kg', 'qty' => 1,  'price' => 0.90, 'compare' => null, 'stock' => 500, 'is_default' => false],
                    ['label' => '5 kg',  'unit' => 'kg', 'qty' => 5,  'price' => 4.00, 'compare' => 4.50, 'stock' => 200, 'is_default' => true],
                    ['label' => '25 kg', 'unit' => 'kg', 'qty' => 25, 'price' => 18.00,'compare' => null, 'stock' => 80,  'is_default' => false],
                ],
            ],
            // Cooking Oil
            [
                'category' => 'Cooking Oil', 'brand' => 'Generic',
                'name' => 'Vegetable Cooking Oil', 'name_so' => 'Saliid Khudaar',
                'slug' => 'vegetable-cooking-oil', 'is_weight_based' => false,
                'base_unit' => 'L', 'tags' => ['oil', 'cooking'],
                'variants' => [
                    ['label' => '1 L',   'unit' => 'L', 'qty' => 1,  'price' => 2.50, 'compare' => null, 'stock' => 300, 'is_default' => false],
                    ['label' => '3 L',   'unit' => 'L', 'qty' => 3,  'price' => 6.50, 'compare' => 7.50, 'stock' => 200, 'is_default' => true],
                    ['label' => '5 L',   'unit' => 'L', 'qty' => 5,  'price' => 10.00,'compare' => null, 'stock' => 120, 'is_default' => false],
                ],
            ],
            // Sugar
            [
                'category' => 'Sugar & Salt', 'brand' => 'Generic',
                'name' => 'White Sugar', 'name_so' => 'Sonkor Cad',
                'slug' => 'white-sugar', 'is_weight_based' => true,
                'base_unit' => 'kg', 'tags' => ['sugar', 'staple'],
                'variants' => [
                    ['label' => '1 kg',  'unit' => 'kg', 'qty' => 1,  'price' => 0.80, 'compare' => null, 'stock' => 400, 'is_default' => false],
                    ['label' => '5 kg',  'unit' => 'kg', 'qty' => 5,  'price' => 3.80, 'compare' => 4.00, 'stock' => 250, 'is_default' => true],
                    ['label' => '50 kg', 'unit' => 'kg', 'qty' => 50, 'price' => 35.00,'compare' => null, 'stock' => 30,  'is_default' => false],
                ],
            ],
            // Water
            [
                'category' => 'Water', 'brand' => 'Candheeye',
                'name' => 'Drinking Water', 'name_so' => 'Biyaha Cabashada',
                'slug' => 'drinking-water', 'is_weight_based' => false,
                'base_unit' => 'L', 'tags' => ['water', 'beverage'],
                'variants' => [
                    ['label' => '500 ml', 'unit' => 'ml', 'qty' => 500, 'price' => 0.30, 'compare' => null, 'stock' => 600, 'is_default' => false],
                    ['label' => '1.5 L',  'unit' => 'L',  'qty' => 1.5, 'price' => 0.60, 'compare' => null, 'stock' => 400, 'is_default' => true],
                    ['label' => '19 L (jug)', 'unit' => 'L', 'qty' => 19, 'price' => 3.50,'compare' => null, 'stock' => 100, 'is_default' => false],
                ],
            ],
            // Eggs
            [
                'category' => 'Dairy & Eggs', 'brand' => 'Iftin Dairy',
                'name' => 'Fresh Eggs', 'name_so' => 'Ugax Cusub',
                'slug' => 'fresh-eggs', 'is_weight_based' => false,
                'base_unit' => 'piece', 'tags' => ['eggs', 'fresh', 'protein'],
                'variants' => [
                    ['label' => '6 pcs',   'unit' => 'piece', 'qty' => 6,   'price' => 1.50, 'compare' => null, 'stock' => 200, 'is_default' => false],
                    ['label' => '12 pcs',  'unit' => 'piece', 'qty' => 12,  'price' => 2.80, 'compare' => 3.00, 'stock' => 150, 'is_default' => true],
                    ['label' => '30 pcs',  'unit' => 'piece', 'qty' => 30,  'price' => 6.50, 'compare' => null, 'stock' => 80,  'is_default' => false],
                ],
            ],
            // Tomatoes
            [
                'category' => 'Vegetables', 'brand' => 'Somali Fresh',
                'name' => 'Fresh Tomatoes', 'name_so' => 'Yaanyo Cusub',
                'slug' => 'fresh-tomatoes', 'is_weight_based' => true,
                'base_unit' => 'kg', 'tags' => ['vegetable', 'fresh'],
                'variants' => [
                    ['label' => '500 g', 'unit' => 'g',  'qty' => 500, 'price' => 0.75, 'compare' => null, 'stock' => 100, 'is_default' => false],
                    ['label' => '1 kg',  'unit' => 'kg', 'qty' => 1,   'price' => 1.30, 'compare' => null, 'stock' => 80,  'is_default' => true],
                    ['label' => '3 kg',  'unit' => 'kg', 'qty' => 3,   'price' => 3.50, 'compare' => 3.90, 'stock' => 40,  'is_default' => false],
                ],
            ],
            // Bananas
            [
                'category' => 'Fruits', 'brand' => 'Somali Fresh',
                'name' => 'Bananas', 'name_so' => 'Moos',
                'slug' => 'bananas', 'is_weight_based' => true,
                'base_unit' => 'kg', 'tags' => ['fruit', 'fresh'],
                'variants' => [
                    ['label' => '1 kg',  'unit' => 'kg', 'qty' => 1, 'price' => 1.00, 'compare' => null, 'stock' => 120, 'is_default' => true],
                    ['label' => '3 kg',  'unit' => 'kg', 'qty' => 3, 'price' => 2.70, 'compare' => 3.00, 'stock' => 60,  'is_default' => false],
                ],
            ],
            // Milk
            [
                'category' => 'Dairy & Eggs', 'brand' => 'Iftin Dairy',
                'name' => 'Fresh Milk', 'name_so' => 'Caano Cusub',
                'slug' => 'fresh-milk', 'is_weight_based' => false,
                'base_unit' => 'L', 'tags' => ['dairy', 'fresh'],
                'variants' => [
                    ['label' => '500 ml', 'unit' => 'ml', 'qty' => 500, 'price' => 0.90, 'compare' => null, 'stock' => 200, 'is_default' => false],
                    ['label' => '1 L',    'unit' => 'L',  'qty' => 1,   'price' => 1.60, 'compare' => null, 'stock' => 150, 'is_default' => true],
                ],
            ],
        ];

        foreach ($products as $pd) {
            $catIdVal   = DB::table('egrocery_categories')->where('name', $pd['category'])->value('id');
            $brandIdVal = $pd['brand'] ? $brandId($pd['brand']) : null;
            $unitIdVal  = $unitId($pd['base_unit']);

            if (!$catIdVal || !$unitIdVal) continue;

            $productId = DB::table('egrocery_products')->updateOrInsert(
                ['slug' => $pd['slug']],
                [
                    'category_id'     => $catIdVal,
                    'brand_id'        => $brandIdVal,
                    'name'            => $pd['name'],
                    'name_so'         => $pd['name_so'],
                    'slug'            => $pd['slug'],
                    'description'     => null,
                    'images'          => json_encode([]),
                    'base_unit_id'    => $unitIdVal,
                    'is_weight_based' => $pd['is_weight_based'],
                    'tags'            => json_encode($pd['tags']),
                    'barcode'         => null,
                    'is_active'       => true,
                    'is_featured'     => in_array($pd['slug'], ['basmati-rice', 'vegetable-cooking-oil', 'fresh-eggs']),
                    'avg_rating'      => round(mt_rand(38, 50) / 10, 1),
                    'orders_count'    => mt_rand(10, 200),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );

            $productId = DB::table('egrocery_products')->where('slug', $pd['slug'])->value('id');

            foreach ($pd['variants'] as $i => $v) {
                $variantUnitId = $unitId($v['unit']);
                $sku = strtoupper(Str::slug($pd['slug'])) . '-' . ($i + 1);
                DB::table('egrocery_product_variants')->updateOrInsert(
                    ['product_id' => $productId, 'label' => $v['label']],
                    [
                        'product_id'         => $productId,
                        'label'              => $v['label'],
                        'unit_id'            => $variantUnitId,
                        'unit_qty'           => $v['qty'],
                        'price'              => $v['price'],
                        'compare_price'      => $v['compare'],
                        'cost'               => null,
                        'sku'                => $sku,
                        'stock_qty'          => $v['stock'],
                        'low_stock_threshold'=> 5,
                        'is_default'         => $v['is_default'],
                        'sort_order'         => $i,
                        'is_active'          => true,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]
                );
            }
        }

        // ── Delivery Zones ────────────────────────────────────────────────────
        DB::table('egrocery_delivery_zones')->insertOrIgnore([
            ['name' => 'Mogadishu Central', 'district_ids' => json_encode([1,2,3]),   'fee' => 2.00,  'min_order' => 10.00, 'free_over' => 30.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mogadishu North',   'district_ids' => json_encode([4,5,6]),   'fee' => 3.00,  'min_order' => 15.00, 'free_over' => 40.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mogadishu South',   'district_ids' => json_encode([7,8]),     'fee' => 3.50,  'min_order' => 15.00, 'free_over' => 50.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Outside Mogadishu', 'district_ids' => json_encode([9,10,11]), 'fee' => 5.00,  'min_order' => 20.00, 'free_over' => null,  'is_active' => false,'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Delivery Slots ────────────────────────────────────────────────────
        DB::table('egrocery_delivery_slots')->insertOrIgnore([
            ['label' => 'Today  8–11 AM',   'day_offset' => 0, 'start_time' => '08:00:00', 'end_time' => '11:00:00', 'capacity' => 30, 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Today  12–3 PM',   'day_offset' => 0, 'start_time' => '12:00:00', 'end_time' => '15:00:00', 'capacity' => 40, 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Today  3–6 PM',    'day_offset' => 0, 'start_time' => '15:00:00', 'end_time' => '18:00:00', 'capacity' => 40, 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Tomorrow  8–11 AM','day_offset' => 1, 'start_time' => '08:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Tomorrow  12–3 PM','day_offset' => 1, 'start_time' => '12:00:00', 'end_time' => '15:00:00', 'capacity' => 50, 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Home Sections ─────────────────────────────────────────────────────
        DB::table('egrocery_sections')->insertOrIgnore([
            ['title' => 'Flash Deals 🔥',       'title_so' => 'Qiimaha Gaaban',    'type' => 'flash_deal',       'layout' => 'h_scroll',    'category_id' => null, 'sort_order' => 1, 'is_active' => true, 'starts_at' => null, 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Best Sellers',          'title_so' => 'Kuwa Ugu Badan',    'type' => 'best_sellers',     'layout' => 'h_scroll',    'category_id' => null, 'sort_order' => 2, 'is_active' => true, 'starts_at' => null, 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'New Arrivals',          'title_so' => 'Cusub Yimid',       'type' => 'new_arrivals',     'layout' => 'h_scroll',    'category_id' => null, 'sort_order' => 3, 'is_active' => true, 'starts_at' => null, 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Fresh Produce',         'title_so' => 'Khudaar & Miro',    'type' => 'category_spotlight','layout' => 'grid_2x',    'category_id' => null, 'sort_order' => 4, 'is_active' => true, 'starts_at' => null, 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Buy Again',             'title_so' => 'Mar Kale Iibso',    'type' => 'buy_again',        'layout' => 'h_scroll',    'category_id' => null, 'sort_order' => 5, 'is_active' => true, 'starts_at' => null, 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Banners ───────────────────────────────────────────────────────────
        DB::table('egrocery_banners')->insertOrIgnore([
            ['title' => 'Fresh Groceries Delivered Fast', 'image' => '/images/egrocery/banner1.jpg', 'placement' => 'home_top', 'link_type' => 'none', 'link_value' => null, 'sort_order' => 1, 'starts_at' => null, 'ends_at' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Up to 20% off Rice & Flour',    'image' => '/images/egrocery/banner2.jpg', 'placement' => 'home_top', 'link_type' => 'none', 'link_value' => null, 'sort_order' => 2, 'starts_at' => null, 'ends_at' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->command->info('✅ eGrocery seed complete: ' .
            DB::table('egrocery_categories')->count() . ' categories, ' .
            DB::table('egrocery_products')->count() . ' products, ' .
            DB::table('egrocery_product_variants')->count() . ' variants.'
        );
    }
}
