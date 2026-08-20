<?php

namespace Database\Seeders\EWholesale;

use App\Models\EWholesale\{
    EWSupplier, EWBuyer, EWCreditAccount,
    EWCategory, EWProduct, EWProductVariant, EWPriceTier,
    EWPriceList, EWBuyerPriceList, EWDeal, EWShippingRule
};
use App\Models\{Vendor, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EWholesaleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding eWholesale catalog…');

        // ── Price Lists ──────────────────────────────────────────────────
        $plRetailer    = EWPriceList::create(['name' => 'Retailer',    'discount_percent' => 2.00]);
        $plDistributor = EWPriceList::create(['name' => 'Distributor', 'discount_percent' => 5.00]);
        $plVip         = EWPriceList::create(['name' => 'VIP',         'discount_percent' => 8.00]);

        // ── Categories ───────────────────────────────────────────────────
        $cats = $this->seedCategories();

        // ── Suppliers ────────────────────────────────────────────────────
        $suppliers = $this->seedSuppliers();

        // ── Products ─────────────────────────────────────────────────────
        $this->seedProducts($suppliers, $cats);

        // ── Buyers (3 seeded) ─────────────────────────────────────────────
        $this->seedBuyers($plDistributor, $plVip);

        // ── Platform shipping default ─────────────────────────────────────
        EWShippingRule::create([
            'supplier_id'    => null,
            'basis'          => 'flat_by_zone',
            'rate'           => 3.00,
            'free_over'      => 500.00,
            'is_active'      => true,
        ]);

        $this->command->info('✅ eWholesale seeding complete.');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function seedCategories(): array
    {
        $roots = [
            ['name'=>'Raashinka Jumlada',     'name_so'=>'Raashinka Jumlada',  'slug'=>'food-staples',    'icon'=>'🌾', 'sort_order'=>1],
            ['name'=>'Cabitaannada',          'name_so'=>'Cabitaannada',        'slug'=>'beverages',       'icon'=>'🥤', 'sort_order'=>2],
            ['name'=>'Nadaafadda',            'name_so'=>'Nadaafadda',          'slug'=>'cleaning',        'icon'=>'🧴', 'sort_order'=>3],
            ['name'=>'Electronics & Phones',  'name_so'=>'Elektronigga',        'slug'=>'electronics',     'icon'=>'📱', 'sort_order'=>4],
            ['name'=>'Dharka & Fabrics',      'name_so'=>'Dharka',              'slug'=>'fabrics',         'icon'=>'🧵', 'sort_order'=>5],
            ['name'=>'Dhismaha',             'name_so'=>'Dhismaha',             'slug'=>'construction',    'icon'=>'🏗️', 'sort_order'=>6],
            ['name'=>'Qalabka Guriga',        'name_so'=>'Qalabka Guriga',      'slug'=>'home-goods',      'icon'=>'🏠', 'sort_order'=>7],
            ['name'=>'Caafimaad & Farmasi',   'name_so'=>'Caafimaad',           'slug'=>'health-pharma',   'icon'=>'💊', 'sort_order'=>8],
        ];

        $result = [];
        foreach ($roots as $d) {
            $result[$d['slug']] = EWCategory::create(array_merge($d, ['is_active' => true]));
        }

        // Sub-categories for food
        EWCategory::create(['parent_id'=>$result['food-staples']->id, 'name'=>'Grain & Rice',    'slug'=>'grain-rice',    'icon'=>'🍚', 'sort_order'=>1, 'is_active'=>true]);
        EWCategory::create(['parent_id'=>$result['food-staples']->id, 'name'=>'Oils & Fats',     'slug'=>'oils-fats',     'icon'=>'🫙', 'sort_order'=>2, 'is_active'=>true]);
        EWCategory::create(['parent_id'=>$result['food-staples']->id, 'name'=>'Sugar & Tea',     'slug'=>'sugar-tea',     'icon'=>'🍵', 'sort_order'=>3, 'is_active'=>true]);
        EWCategory::create(['parent_id'=>$result['beverages']->id,    'name'=>'Water',           'slug'=>'water',         'icon'=>'💧', 'sort_order'=>1, 'is_active'=>true]);
        EWCategory::create(['parent_id'=>$result['beverages']->id,    'name'=>'Soft Drinks',     'slug'=>'soft-drinks',   'icon'=>'🥫', 'sort_order'=>2, 'is_active'=>true]);
        EWCategory::create(['parent_id'=>$result['electronics']->id,  'name'=>'Smartphones',    'slug'=>'smartphones',   'icon'=>'📱', 'sort_order'=>1, 'is_active'=>true]);
        EWCategory::create(['parent_id'=>$result['electronics']->id,  'name'=>'Accessories',    'slug'=>'electronics-accessories', 'icon'=>'🎧', 'sort_order'=>2, 'is_active'=>true]);

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function seedSuppliers(): array
    {
        // Create or reuse vendors for the 6 suppliers
        $supplierDefs = [
            ['name' => 'Banadir Import & Export',    'verification' => 'gold',         'about' => 'Somalia\'s largest food staples importer since 2005. Gold-verified, 200+ SKUs.'],
            ['name' => 'Juba Distributors',          'verification' => 'verified',     'about' => 'Regional distributor covering Banadir, Middle Shabelle, and Lower Shabelle.'],
            ['name' => 'Hormuud Traders',            'verification' => 'verified',     'about' => 'Electronics and mobile accessories wholesale. Authorized dealer for major brands.'],
            ['name' => 'Ogaal Foods Wholesale',      'verification' => 'verified',     'about' => 'Specialized in beverages, dairy, and processed foods. ISO-certified warehouse.'],
            ['name' => 'Daaruusalaam Electronics',   'verification' => 'unverified',   'about' => 'Consumer electronics and appliances.'],
            ['name' => 'Sahal General Trading',      'verification' => 'unverified',   'about' => 'General merchandise and construction materials.'],
        ];

        $suppliers = [];
        foreach ($supplierDefs as $def) {
            // Create a dummy vendor if needed
            $vendor = Vendor::firstOrCreate(
                ['name' => $def['name']],
                [
                    'email'       => Str::slug($def['name']) . '@ewholesale.test',
                    'phone'       => '+252' . rand(610000000, 699999999),
                    'status'      => 'active',
                    'is_active'   => true,
                    'is_approved' => true,
                ]
            );

            $supplier = EWSupplier::firstOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'display_name'     => $def['name'],
                    'about'            => $def['about'],
                    'verification'     => $def['verification'],
                    'verified_at'      => in_array($def['verification'], ['verified','gold']) ? now()->subMonths(rand(1,12)) : null,
                    'response_rate'    => rand(75, 98) + rand(0,9)/10,
                    'response_time_avg'=> rand(30, 240),
                    'rating'           => round(rand(38, 50) / 10, 1),
                    'is_active'        => true,
                ]
            );

            $suppliers[Str::slug($def['name'])] = $supplier;
        }

        return $suppliers;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function seedProducts(array $suppliers, array $cats): void
    {
        $banadir = $suppliers['banadir-import-export'];
        $juba    = $suppliers['juba-distributors'];
        $hormuud = $suppliers['hormuud-traders'];
        $ogaal   = $suppliers['ogaal-foods-wholesale'];
        $sahal   = $suppliers['sahal-general-trading'];

        $food  = $cats['food-staples'];
        $bev   = $cats['beverages'];
        $clean = $cats['cleaning'];
        $elec  = $cats['electronics'];
        $fab   = $cats['fabrics'];
        $home  = $cats['home-goods'];
        $build = $cats['construction'];
        $health= $cats['health-pharma'];

        // ── FOOD STAPLES ──────────────────────────────────────────────────

        // 1. Bariis Basmati 25kg
        $basmati = $this->product($banadir, $food, [
            'name'         => 'Bariis Basmati 25kg',
            'name_so'      => 'Bariis Basmati 25kg',
            'unit'         => 'sack',
            'moq'          => 10,
            'lead_time_days' => 2,
            'origin_country' => 'Pakistan',
            'brand'        => 'Kernel',
            'is_featured'  => true,
            'specs'        => [['key'=>'Weight','value'=>'25kg'],['key'=>'Grain','value'=>'Long grain'],['key'=>'Aroma','value'=>'Aged']],
        ], [
            [10, 49, 38.00],
            [50, 199, 36.00],
            [200, null, 34.00],
        ]);

        // 2. Saliid Cad 5L carton (4 pcs)
        $oil = $this->product($banadir, $food, [
            'name'          => 'Saliid Cad 5L (carton 4 pcs)',
            'name_so'       => 'Saliid Cad 5L',
            'unit'          => 'carton',
            'units_per_pack'=> 4,
            'moq'           => 5,
            'lead_time_days'=> 1,
            'origin_country'=> 'Malaysia',
            'brand'         => 'Palm Gold',
            'is_featured'   => true,
            'specs'         => [['key'=>'Contents','value'=>'4 × 5L bottles'],['key'=>'Type','value'=>'Palm Oil']],
        ], [
            [5, 19, 46.00],
            [20, 99, 44.00],
            [100, null, 41.50],
        ]);

        // 3. Sonkor 50kg sack
        $sugar = $this->product($banadir, $food, [
            'name'          => 'Sonkor 50kg',
            'name_so'       => 'Sonkor 50kg',
            'unit'          => 'sack',
            'moq'           => 20,
            'lead_time_days'=> 1,
            'origin_country'=> 'Brazil',
            'brand'         => 'Refined White',
            'specs'         => [['key'=>'Weight','value'=>'50kg'],['key'=>'Grade','value'=>'ICUMSA 45']],
        ], [
            [20, 49, 29.00],
            [50, 199, 27.50],
            [200, null, 26.00],
        ]);

        // 4. Bariis Indian 50kg sack
        $indianRice = $this->product($juba, $food, [
            'name'          => 'Bariis Indian 50kg',
            'name_so'       => 'Bariis Hindi 50kg',
            'unit'          => 'sack',
            'moq'           => 10,
            'lead_time_days'=> 3,
            'origin_country'=> 'India',
            'brand'         => 'Golden Grain',
            'specs'         => [['key'=>'Weight','value'=>'50kg'],['key'=>'Type','value'=>'Parboiled']],
        ], [
            [10, 49, 52.00],
            [50, 199, 49.50],
            [200, null, 47.00],
        ]);

        // 5. Shaah Adeni — with variant: original vs extra strong
        $tea = $this->product($banadir, $food, [
            'name'          => 'Shaah Adeni Carton',
            'name_so'       => 'Shaah Adeni',
            'unit'          => 'carton',
            'units_per_pack'=> 48,
            'moq'           => 10,
            'lead_time_days'=> 1,
            'origin_country'=> 'Kenya',
            'brand'         => 'Safari',
            'specs'         => [['key'=>'Contents','value'=>'48 × 250g bags per carton']],
        ], [
            [10, 49, 18.50],
            [50, 199, 17.00],
            [200, null, 15.75],
        ], variants: [
            ['attributes' => ['blend'=>'Original'],      'stock_qty' => 500, 'is_default' => true],
            ['attributes' => ['blend'=>'Extra Strong'],  'stock_qty' => 200],
        ]);

        // 6. Barawe Long Grain 50kg
        $this->product($juba, $food, [
            'name'=> 'Barawe Long Grain Rice 50kg', 'name_so'=>'Bariis Barawe 50kg',
            'unit'=>'sack','moq'=>10,'lead_time_days'=>2,'origin_country'=>'Somalia',
            'specs'=>[['key'=>'Weight','value'=>'50kg']],
        ], [
            [10,49,55.00],[50,199,52.50],[200,null,50.00],
        ]);

        // 7. Hilibka Pasta 10kg (carton 12 pcs)
        $this->product($juba, $food, [
            'name'=> 'Pasta 500g (carton 12kg)', 'name_so'=>'Baasto',
            'unit'=>'carton','units_per_pack'=>24,'moq'=>10,'lead_time_days'=>1,'origin_country'=>'Turkey',
            'brand'=>'Golden',
            'specs'=>[['key'=>'Pieces','value'=>'24 × 500g']],
        ], [
            [10,49,14.00],[50,199,13.00],[200,null,12.00],
        ]);

        // ── BEVERAGES ─────────────────────────────────────────────────────

        // 8. Biyo 600ml (carton 24 bottles)
        $water = $this->product($ogaal, $bev, [
            'name'          => 'Biyo 600ml (carton 24)',
            'name_so'       => 'Biyo 600ml',
            'unit'          => 'carton',
            'units_per_pack'=> 24,
            'moq'           => 50,
            'lead_time_days'=> 0,
            'origin_country'=> 'Somalia',
            'brand'         => 'Hayat',
            'is_featured'   => true,
            'specs'         => [['key'=>'Volume','value'=>'600ml × 24 bottles']],
        ], [
            [50, 199, 4.80],
            [200, null, 4.40],
        ]);

        // 9. Biyo 1.5L carton 12
        $this->product($ogaal, $bev, [
            'name'=>'Biyo 1.5L (carton 12)','name_so'=>'Biyo 1.5L',
            'unit'=>'carton','units_per_pack'=>12,'moq'=>20,'lead_time_days'=>0,'origin_country'=>'Somalia','brand'=>'Hayat',
            'specs'=>[['key'=>'Volume','value'=>'1.5L × 12 bottles']],
        ], [
            [20,99,6.50],[100,null,5.90],
        ]);

        // 10. Coca-Cola 330ml (carton 24)
        $this->product($ogaal, $bev, [
            'name'=>'Coca-Cola 330ml (carton 24)','name_so'=>'Cola',
            'unit'=>'carton','units_per_pack'=>24,'moq'=>20,'lead_time_days'=>1,'origin_country'=>'Ethiopia','brand'=>'Coca-Cola',
            'specs'=>[['key'=>'Volume','value'=>'330ml × 24 cans']],
        ], [
            [20,99,19.00],[100,499,17.50],[500,null,16.00],
        ]);

        // 11. Vimto Cordial 710ml carton 12
        $this->product($ogaal, $bev, [
            'name'=>'Vimto Cordial 710ml (carton 12)','name_so'=>'Vimto',
            'unit'=>'carton','units_per_pack'=>12,'moq'=>10,'lead_time_days'=>2,'origin_country'=>'UAE','brand'=>'Vimto',
        ], [
            [10,49,28.00],[50,null,26.00],
        ]);

        // ── CLEANING ──────────────────────────────────────────────────────

        // 12. Omo Washing Powder 4kg (carton 4 pcs)
        $this->product($juba, $clean, [
            'name'=>'Omo Washing Powder 4kg (carton 4)','name_so'=>'Omo 4kg',
            'unit'=>'carton','units_per_pack'=>4,'moq'=>5,'lead_time_days'=>1,'origin_country'=>'Kenya','brand'=>'Omo',
        ], [
            [5,19,32.00],[20,99,30.50],[100,null,29.00],
        ]);

        // 13. Ariel 2.5kg carton 6
        $this->product($juba, $clean, [
            'name'=>'Ariel 2.5kg (carton 6)','name_so'=>'Ariel',
            'unit'=>'carton','units_per_pack'=>6,'moq'=>5,'lead_time_days'=>1,'origin_country'=>'Egypt','brand'=>'Ariel',
        ], [
            [5,19,36.00],[20,99,34.00],[100,null,32.00],
        ]);

        // 14. Flash Bleach 1L carton 12
        $this->product($juba, $clean, [
            'name'=>'Bleach 1L (carton 12)','name_so'=>'Balish',
            'unit'=>'carton','units_per_pack'=>12,'moq'=>10,'lead_time_days'=>1,'origin_country'=>'Kenya','brand'=>'Flash',
        ], [
            [10,49,9.00],[50,null,8.00],
        ]);

        // ── ELECTRONICS ───────────────────────────────────────────────────

        // 15. Smartphone — with size & color variants
        $phone = $this->product($hormuud, $elec, [
            'name'          => 'Budget Smartphone X1',
            'name_so'       => 'Telefoonka X1',
            'unit'          => 'piece',
            'moq'           => 10,
            'lead_time_days'=> 5,
            'origin_country'=> 'China',
            'brand'         => 'TechX',
            'is_featured'   => true,
            'specs'         => [
                ['key'=>'Display','value'=>'6.5" HD+'],
                ['key'=>'Battery','value'=>'5000mAh'],
                ['key'=>'Camera','value'=>'13MP'],
                ['key'=>'OS','value'=>'Android 13'],
            ],
        ], [
            [10, 49, 62.00],
            [50, 199, 58.00],
            [200, null, 54.00],
        ], variants: [
            ['attributes'=>['storage'=>'64GB','color'=>'Black'],  'sku'=>'X1-64-BLK', 'stock_qty'=>150, 'weight_kg'=>0.185, 'is_default'=>true],
            ['attributes'=>['storage'=>'64GB','color'=>'Blue'],   'sku'=>'X1-64-BLU', 'stock_qty'=>80,  'weight_kg'=>0.185],
            ['attributes'=>['storage'=>'128GB','color'=>'Black'], 'sku'=>'X1-128-BLK','stock_qty'=>100, 'weight_kg'=>0.185],
            ['attributes'=>['storage'=>'128GB','color'=>'Gold'],  'sku'=>'X1-128-GLD','stock_qty'=>60,  'weight_kg'=>0.185],
        ]);

        // 16. iPhone Charging Cable 1m (carton 100)
        $this->product($hormuud, $elec, [
            'name'=>'USB-C Cable 1m (box 100)','name_so'=>'Xarig Cas',
            'unit'=>'box','units_per_pack'=>100,'moq'=>2,'lead_time_days'=>3,'origin_country'=>'China','brand'=>'FastCharge',
            'specs'=>[['key'=>'Length','value'=>'1m'],['key'=>'Speed','value'=>'65W fast charge']],
        ], [
            [2,9,28.00],[10,49,25.00],[50,null,22.00],
        ]);

        // 17. Power Bank 10000mAh (box 20)
        $this->product($hormuud, $elec, [
            'name'=>'Power Bank 10000mAh (box 20)','name_so'=>'Bateri Xoog',
            'unit'=>'box','units_per_pack'=>20,'moq'=>2,'lead_time_days'=>5,'origin_country'=>'China','brand'=>'TechX',
            'specs'=>[['key'=>'Capacity','value'=>'10000mAh'],['key'=>'Ports','value'=>'2 USB + 1 USB-C']],
        ], [
            [2,9,75.00],[10,49,70.00],[50,null,65.00],
        ]);

        // ── FABRICS ───────────────────────────────────────────────────────

        // 18. Dirac Fabric Bundle (with color variants)
        $dirac = $this->product($sahal, $fab, [
            'name'          => 'Dirac Fabric Bundle (5m)',
            'name_so'       => 'Diraacdaha Midabka',
            'unit'          => 'piece',
            'moq'           => 20,
            'lead_time_days'=> 7,
            'origin_country'=> 'India',
            'brand'         => 'Bombay Fabrics',
            'is_featured'   => true,
            'specs'         => [['key'=>'Length','value'=>'5m per bundle'],['key'=>'Material','value'=>'Cotton-Polyester blend']],
        ], [
            [20, 99, 8.50],
            [100, 499, 7.75],
            [500, null, 7.00],
        ], variants: [
            ['attributes'=>['color'=>'Red Flower Pattern'],   'stock_qty'=>200, 'weight_kg'=>0.4, 'is_default'=>true],
            ['attributes'=>['color'=>'Blue Floral'],          'stock_qty'=>180, 'weight_kg'=>0.4],
            ['attributes'=>['color'=>'Gold Embroidery'],      'stock_qty'=>120, 'weight_kg'=>0.4],
            ['attributes'=>['color'=>'Black Plain'],          'stock_qty'=>150, 'weight_kg'=>0.4],
            ['attributes'=>['color'=>'Green Traditional'],    'stock_qty'=>100, 'weight_kg'=>0.4],
        ]);

        // 19. Men's Macawiis (carton 50 pcs)
        $this->product($sahal, $fab, [
            'name'=>"Men's Macawiis (carton 50)",'name_so'=>'Macawiis',
            'unit'=>'carton','units_per_pack'=>50,'moq'=>5,'lead_time_days'=>7,'origin_country'=>'India',
            'specs'=>[['key'=>'Pieces','value'=>'50 per carton'],['key'=>'Size','value'=>'Standard adult']],
        ], [
            [5,19,55.00],[20,99,52.00],[100,null,48.00],
        ]);

        // 20. Garbasaar (head-scarf) carton 100
        $this->product($sahal, $fab, [
            'name'=>'Garbasaar Scarf (box 100)','name_so'=>'Garbasaar',
            'unit'=>'box','units_per_pack'=>100,'moq'=>2,'lead_time_days'=>5,'origin_country'=>'China',
        ], [
            [2,9,18.00],[10,null,15.00],
        ]);

        // ── CONSTRUCTION ─────────────────────────────────────────────────

        // 21. Cement 50kg sack
        $this->product($sahal, $build, [
            'name'=>'Cement 50kg Sack','name_so'=>'Simento 50kg',
            'unit'=>'sack','moq'=>50,'lead_time_days'=>3,'origin_country'=>'UAE','brand'=>'Oman Cement',
            'specs'=>[['key'=>'Grade','value'=>'OPC 42.5N'],['key'=>'Weight','value'=>'50kg']],
        ], [
            [50,199,8.50],[200,999,8.00],[1000,null,7.60],
        ]);

        // 22. Steel Rebar 12mm (bundle 10 bars)
        $this->product($sahal, $build, [
            'name'=>'Steel Rebar 12mm (bundle 10 bars)','name_so'=>'Birta Dhismaha',
            'unit'=>'bundle','moq'=>10,'lead_time_days'=>5,'origin_country'=>'Turkey',
            'specs'=>[['key'=>'Diameter','value'=>'12mm'],['key'=>'Length','value'=>'12m'],['key'=>'Bars','value'=>'10 per bundle']],
        ], [
            [10,49,48.00],[50,199,46.00],[200,null,44.00],
        ]);

        // 23. Paint 20L (White Emulsion)
        $this->product($sahal, $build, [
            'name'=>'White Emulsion Paint 20L','name_so'=>'Bojoog 20L',
            'unit'=>'drum','moq'=>5,'lead_time_days'=>2,'origin_country'=>'Kenya','brand'=>'Crown',
            'specs'=>[['key'=>'Volume','value'=>'20L'],['key'=>'Type','value'=>'Emulsion'],['key'=>'Finish','value'=>'Matt']],
        ], [
            [5,19,22.00],[20,99,20.50],[100,null,19.00],
        ]);

        // ── HOME GOODS ────────────────────────────────────────────────────

        // 24. Plastic Chairs (carton 4)
        $this->product($sahal, $home, [
            'name'=>'Plastic Chair (pack of 4)','name_so'=>'Kursi Caag',
            'unit'=>'carton','units_per_pack'=>4,'moq'=>5,'lead_time_days'=>2,'origin_country'=>'China',
        ], [
            [5,19,24.00],[20,99,22.00],[100,null,20.00],
        ]);

        // 25. Cooking Pot Set (carton 6 sets)
        $this->product($sahal, $home, [
            'name'=>'Stainless Cooking Pot Set 5-piece (carton 6)','name_so'=>'Digsi Bir',
            'unit'=>'carton','units_per_pack'=>6,'moq'=>3,'lead_time_days'=>3,'origin_country'=>'China',
            'specs'=>[['key'=>'Pieces per set','value'=>'5 pots'],['key'=>'Material','value'=>'Stainless Steel']],
        ], [
            [3,9,65.00],[10,49,60.00],[50,null,55.00],
        ]);

        // 26. Mattress (single, carton 10)
        $this->product($sahal, $home, [
            'name'=>'Foam Mattress Single (10-pack)','name_so'=>'Burburkii',
            'unit'=>'carton','units_per_pack'=>10,'moq'=>2,'lead_time_days'=>3,'origin_country'=>'Somalia',
            'specs'=>[['key'=>'Size','value'=>'90×190cm'],['key'=>'Thickness','value'=>'10cm']],
        ], [
            [2,4,120.00],[5,19,115.00],[20,null,108.00],
        ]);

        // ── HEALTH & PHARMA ───────────────────────────────────────────────

        // 27. ORS Sachet (carton 400)
        $this->product($juba, $health, [
            'name'=>'ORS Rehydration Sachet (carton 400)','name_so'=>'Daawo Biyo-luminta',
            'unit'=>'carton','units_per_pack'=>400,'moq'=>2,'lead_time_days'=>2,'origin_country'=>'Kenya','brand'=>'Bioglan',
            'specs'=>[['key'=>'Sachets per carton','value'=>'400']],
        ], [
            [2,9,22.00],[10,49,20.00],[50,null,18.50],
        ]);

        // 28. Paracetamol 500mg strip 10 (carton 1000 strips)
        $this->product($juba, $health, [
            'name'=>'Paracetamol 500mg (carton 1000 strips)','name_so'=>'Paracetamol',
            'unit'=>'carton','units_per_pack'=>1000,'moq'=>1,'lead_time_days'=>3,'origin_country'=>'India','brand'=>'Generic',
            'specs'=>[['key'=>'Dose','value'=>'500mg'],['key'=>'Per strip','value'=>'10 tablets']],
        ], [
            [1,4,38.00],[5,19,35.00],[20,null,32.00],
        ]);

        // 29. Surgical Gloves Box 100 (carton 10 boxes)
        $this->product($juba, $health, [
            'name'=>'Surgical Gloves Medium (carton 10 boxes)','name_so'=>'Gacmojiis',
            'unit'=>'carton','units_per_pack'=>10,'moq'=>2,'lead_time_days'=>2,'origin_country'=>'Malaysia','brand'=>'SafeHands',
            'specs'=>[['key'=>'Size','value'=>'Medium'],['key'=>'Gloves per box','value'=>'100'],['key'=>'Material','value'=>'Latex']],
        ], [
            [2,9,28.00],[10,49,25.50],[50,null,23.00],
        ]);

        // 30. Face Mask 3-ply carton 2000
        $this->product($juba, $health, [
            'name'=>'3-Ply Face Mask (carton 2000)','name_so'=>'Maaskarada',
            'unit'=>'carton','units_per_pack'=>2000,'moq'=>1,'lead_time_days'=>2,'origin_country'=>'China',
            'spec'=>[['key'=>'Per carton','value'=>'2000 masks'],['key'=>'Layers','value'=>'3-ply']],
        ], [
            [1,4,14.00],[5,19,12.50],[20,null,11.00],
        ]);

        // ── MORE FOOD (total ~40) ─────────────────────────────────────────

        // 31. Muufo Flour 50kg
        $this->product($banadir, $food, [
            'name'=>'Flour 50kg','name_so'=>'Bur 50kg','unit'=>'sack','moq'=>10,'lead_time_days'=>1,
            'origin_country'=>'Ethiopia','brand'=>'Crown',
            'specs'=>[['key'=>'Weight','value'=>'50kg'],['key'=>'Type','value'=>'All-purpose wheat']],
        ],[[10,49,28.50],[50,199,27.00],[200,null,25.50]]);

        // 32. Caano Xoolo (Powdered Milk 2.5kg carton 6)
        $this->product($ogaal, $food, [
            'name'=>'Powdered Milk 2.5kg (carton 6)','name_so'=>'Caano Xoolo',
            'unit'=>'carton','units_per_pack'=>6,'moq'=>5,'lead_time_days'=>2,'origin_country'=>'Netherlands','brand'=>'Nido',
            'specs'=>[['key'=>'Per carton','value'=>'6 × 2.5kg tins']],
        ],[[5,19,68.00],[20,99,65.00],[100,null,62.00]]);

        // 33. Tomato Paste 70g (carton 100)
        $this->product($juba, $food, [
            'name'=>'Tomato Paste 70g (carton 100)','name_so'=>'Tamaandho',
            'unit'=>'carton','units_per_pack'=>100,'moq'=>10,'lead_time_days'=>1,'origin_country'=>'China','brand'=>'Crown',
        ],[[10,49,9.00],[50,199,8.20],[200,null,7.50]]);

        // 34. Macaroni 500g (carton 20)
        $this->product($juba, $food, [
            'name'=>'Macaroni 500g (carton 20)','name_so'=>'Macrooni','unit'=>'carton',
            'units_per_pack'=>20,'moq'=>10,'lead_time_days'=>1,'origin_country'=>'Turkey',
        ],[[10,49,11.00],[50,199,10.00],[200,null,9.00]]);

        // 35. Canned Tuna 185g (carton 48)
        $this->product($ogaal, $food, [
            'name'=>'Canned Tuna 185g (carton 48)','name_so'=>'Tino',
            'unit'=>'carton','units_per_pack'=>48,'moq'=>5,'lead_time_days'=>3,'origin_country'=>'Thailand','brand'=>'John West',
        ],[[5,19,44.00],[20,99,42.00],[100,null,39.50]]);

        // 36. Sardines 125g (carton 50)
        $this->product($ogaal, $food, [
            'name'=>'Sardines 125g (carton 50)','name_so'=>'Sardiin','unit'=>'carton',
            'units_per_pack'=>50,'moq'=>10,'lead_time_days'=>2,'origin_country'=>'Morocco',
        ],[[10,49,26.00],[50,199,24.50],[200,null,23.00]]);

        // 37. Salt 1kg (carton 20)
        $this->product($banadir, $food, [
            'name'=>'Iodized Salt 1kg (carton 20)','name_so'=>'Cusbo','unit'=>'carton',
            'units_per_pack'=>20,'moq'=>20,'lead_time_days'=>1,'origin_country'=>'Somalia',
        ],[[20,99,4.20],[100,499,3.80],[500,null,3.50]]);

        // 38. Dishwashing Liquid 5L (carton 4)
        $this->product($juba, $clean, [
            'name'=>'Dishwashing Liquid 5L (carton 4)','name_so'=>'Saboon Qashinka',
            'unit'=>'carton','units_per_pack'=>4,'moq'=>5,'lead_time_days'=>1,'origin_country'=>'Kenya','brand'=>'Finish',
        ],[[5,19,22.00],[20,99,20.50],[100,null,19.00]]);

        // 39. Shampoo 400ml (carton 24)
        $this->product($ogaal, $health, [
            'name'=>'Shampoo 400ml (carton 24)','name_so'=>'Shambu','unit'=>'carton',
            'units_per_pack'=>24,'moq'=>5,'lead_time_days'=>2,'origin_country'=>'UAE','brand'=>'Head & Shoulders',
        ],[[5,19,38.00],[20,99,36.00],[100,null,34.00]]);

        // 40. AA Batteries 40-pack (carton 50 packs)
        $this->product($hormuud, $elec, [
            'name'=>'AA Batteries 40-pack (carton 50)','name_so'=>'Batariyad',
            'unit'=>'carton','units_per_pack'=>50,'moq'=>2,'lead_time_days'=>2,'origin_country'=>'China','brand'=>'Energizer',
            'specs'=>[['key'=>'Per pack','value'=>'40 AA batteries']],
        ],[[2,9,35.00],[10,49,32.00],[50,null,29.00]]);

        // ── ACTIVE DEALS (2 products) ─────────────────────────────────────
        EWDeal::create([
            'product_id'             => $basmati->id,
            'deal_price_percent_off' => 8.00,
            'min_qty'                => 50,
            'starts_at'              => now()->subDay(),
            'ends_at'                => now()->addDays(7),
            'qty_limit'              => 500,
            'qty_sold'               => 0,
            'is_active'              => true,
        ]);

        EWDeal::create([
            'product_id'             => $water->id,
            'deal_price_percent_off' => 5.00,
            'min_qty'                => 200,
            'starts_at'              => now()->subDay(),
            'ends_at'                => now()->addDays(14),
            'qty_limit'              => null,
            'qty_sold'               => 0,
            'is_active'              => true,
        ]);

        $this->command->info("  📦 Seeded 40 products with tier ladders + 2 active deals.");
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function seedBuyers(EWPriceList $plDistributor, EWPriceList $plVip): void
    {
        $buyerDefs = [
            [
                'name'          => 'Barwaaqo Supermarket',
                'type'          => 'minimarket',
                'kyb'           => 'approved',
                'credit_limit'  => 2000.00,
                'term'          => 'net15',
                'price_list'    => $plDistributor,
            ],
            [
                'name'          => 'Cali Dukaan Store',
                'type'          => 'shop',
                'kyb'           => 'approved',
                'credit_limit'  => 2000.00,
                'term'          => 'net15',
                'price_list'    => $plDistributor,
            ],
            [
                'name'          => 'Hawa Restaurant Group',
                'type'          => 'restaurant',
                'kyb'           => 'approved',
                'credit_limit'  => 2000.00,
                'term'          => 'net15',
                'price_list'    => $plVip,
            ],
        ];

        foreach ($buyerDefs as $def) {
            // Find or create a test user
            $user = User::firstOrCreate(
                ['email' => Str::slug($def['name']) . '@ewholesale.test'],
                [
                    'name'     => $def['name'],
                    'phone'    => '+252' . rand(610000000, 699999999),
                    'password' => bcrypt('password'),
                ]
            );

            $buyer = EWBuyer::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $def['name'],
                    'business_type' => $def['type'],
                    'kyb_status'    => $def['kyb'],
                    'approved_at'   => $def['kyb'] === 'approved' ? now()->subMonth() : null,
                ]
            );

            // Credit account (1 per buyer, approved)
            if (!$buyer->creditAccount) {
                EWCreditAccount::create([
                    'buyer_id'     => $buyer->id,
                    'credit_limit' => $def['credit_limit'],
                    'balance_used' => 0,
                    'term'         => $def['term'],
                    'status'       => 'active',
                ]);
            }

            // Price list assignment
            if (!EWBuyerPriceList::where('buyer_id', $buyer->id)->exists()) {
                EWBuyerPriceList::create([
                    'buyer_id'      => $buyer->id,
                    'price_list_id' => $def['price_list']->id,
                ]);
            }
        }

        $this->command->info('  👥 Seeded 3 buyers with approved credit accounts ($2,000 net15).');
    }

    // ── Helper: create product + variants + tiers ─────────────────────────────

    private function product(
        EWSupplier $supplier,
        EWCategory $category,
        array $attrs,
        array $tiers,    // [[min, max|null, price], ...]
        array $variants = [],  // optional explicit variants
    ): EWProduct {
        $slug = Str::slug($attrs['name']) . '-' . Str::random(4);

        $product = EWProduct::create(array_merge([
            'supplier_id'    => $supplier->id,
            'category_id'    => $category->id,
            'slug'           => $slug,
            'status'         => 'active',
            'orders_count'   => rand(0, 800),
            'lead_time_days' => 1,
        ], $attrs, [
            'specs' => isset($attrs['specs']) ? $attrs['specs'] : null,
        ]));

        // Tiers
        $prices = [];
        foreach ($tiers as [$min, $max, $price]) {
            EWPriceTier::create([
                'product_id' => $product->id,
                'variant_id' => null,
                'min_qty'    => $min,
                'max_qty'    => $max,
                'unit_price' => $price,
            ]);
            $prices[] = $price;
        }

        $product->update(['min_price' => min($prices), 'max_price' => max($prices)]);

        // Variants
        if (empty($variants)) {
            EWProductVariant::create([
                'product_id' => $product->id,
                'stock_qty'  => rand(50, 2000),
                'is_default' => true,
                'is_active'  => true,
            ]);
        } else {
            foreach ($variants as $i => $vData) {
                EWProductVariant::create(array_merge([
                    'product_id' => $product->id,
                    'is_active'  => true,
                    'is_default' => false,
                ], $vData));
            }
        }

        return $product;
    }
}
