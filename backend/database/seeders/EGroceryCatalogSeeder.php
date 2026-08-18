<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Full Somali grocery catalog seed.
 * ~14 categories, ~60 products, ~140 variants.
 * Idempotent: updateOrInsert on slug/name.
 */
class EGroceryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // ── Helpers ───────────────────────────────────────────────────────────
        $uid  = fn($name) => DB::table('egrocery_units')->where('name', $name)->value('id');
        $bid  = fn($name) => DB::table('egrocery_brands')->where('name', $name)->value('id');
        $cid  = fn($slug) => DB::table('egrocery_categories')->where('slug', $slug)->value('id');

        $now = now();
        $ins = fn($table, $data) => DB::table($table)->updateOrInsert(
            ['slug' => $data['slug']],
            array_merge($data, ['created_at' => $now, 'updated_at' => $now])
        );

        // ── Extra brands ──────────────────────────────────────────────────────
        foreach ([
            'Barwaaqo','Al Noor','Hanan Mills','Iftin Dairy','Candheeye',
            'Daallo Foods','Somali Fresh','Generic','Omo','Dove','Pampers',
            'Lipton','Adani Chai','Dano','Milo','Nido','Downy','Ariel',
        ] as $b) {
            DB::table('egrocery_brands')->updateOrInsert(['name' => $b], [
                'name' => $b, 'logo' => null, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── Wipe old demo categories and rebuild clean ─────────────────────────
        // We keep existing if slug matches (idempotent)

        $parentCats = [
            ['slug' => 'khudaar-furuutyo',  'name' => 'Fruits & Vegetables',  'name_so' => 'Khudaar & Furuutyo',       'icon' => '🥦', 'sort' => 1],
            ['slug' => 'hilib-kalluun',     'name' => 'Meat & Fish',           'name_so' => 'Hilib & Kalluun',          'icon' => '🥩', 'sort' => 2],
            ['slug' => 'caano-ukun',        'name' => 'Dairy & Eggs',          'name_so' => 'Caano & Ukun',             'icon' => '🥛', 'sort' => 3],
            ['slug' => 'rooti-bakery',      'name' => 'Bakery',                'name_so' => 'Rooti & Bakery',           'icon' => '🍞', 'sort' => 4],
            ['slug' => 'bariis-baasto-burr','name' => 'Grains, Rice & Pasta',  'name_so' => 'Bariis, Baasto & Burr',   'icon' => '🌾', 'sort' => 5],
            ['slug' => 'saliid-xawaash',    'name' => 'Oil & Spices',          'name_so' => 'Saliid & Xawaash',        'icon' => '🫙', 'sort' => 6],
            ['slug' => 'cabitaanno',        'name' => 'Beverages',             'name_so' => 'Cabitaanno',               'icon' => '🥤', 'sort' => 7],
            ['slug' => 'biyo',              'name' => 'Water',                 'name_so' => 'Biyo',                     'icon' => '💧', 'sort' => 8],
            ['slug' => 'snacks-nacnac',     'name' => 'Snacks',                'name_so' => 'Snacks & Nacnac',          'icon' => '🍿', 'sort' => 9],
            ['slug' => 'qasacado',          'name' => 'Canned & Jarred',       'name_so' => 'Qasacado',                 'icon' => '🥫', 'sort' => 10],
            ['slug' => 'nadaafadda-guriga', 'name' => 'Household Cleaning',    'name_so' => 'Nadaafadda Guriga',        'icon' => '🧹', 'sort' => 11],
            ['slug' => 'dharka-dhaqid',     'name' => 'Laundry',               'name_so' => 'Dharka-dhaqid',            'icon' => '🧺', 'sort' => 12],
            ['slug' => 'daryeelka-shakhsiga','name' => 'Personal Care',        'name_so' => 'Daryeelka Shakhsiga',     'icon' => '🧴', 'sort' => 13],
            ['slug' => 'ilmaha',            'name' => 'Baby Care',             'name_so' => 'Ilmaha',                   'icon' => '👶', 'sort' => 14],
        ];

        foreach ($parentCats as $p) {
            DB::table('egrocery_categories')->updateOrInsert(['slug' => $p['slug']], [
                'parent_id' => null, 'name' => $p['name'], 'name_so' => $p['name_so'],
                'slug' => $p['slug'], 'icon' => $p['icon'], 'image' => null,
                'sort_order' => $p['sort'], 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── Products — grouped by category ────────────────────────────────────
        // Format: [category_slug, brand, name, name_so, slug, is_weight_based, base_unit, tags[], variants[]]
        // variant: [label, unit, qty, price, compare?, stock, is_default]

        $catalog = [

            // ── GRAINS, RICE & PASTA ──────────────────────────────────────────
            ['bariis-baasto-burr','Barwaaqo','Basmati Rice','Bariis Basmati','basmati-rice',true,'kg',
             ['bariis','rice','staple'],
             [
                 ['1 kg', 'kg', 1, 1.80, null,  200, false],
                 ['5 kg', 'kg', 5, 8.50, 9.50,  100, true],
                 ['25 kg bag','bag',25,39.00,null,40,false],
             ]],
            ['bariis-baasto-burr','Al Noor','Egyptian Rice','Bariis Masar','egyptian-rice',true,'kg',
             ['bariis','rice'],
             [
                 ['1 kg', 'kg', 1, 1.40, null,  300, false],
                 ['5 kg', 'kg', 5, 6.50, 7.00,  150, true],
                 ['25 kg bag','bag',25,28.00,null,60,false],
             ]],
            ['bariis-baasto-burr','Hanan Mills','Wheat Flour','Bur Sarreen','wheat-flour',true,'kg',
             ['bur','flour','baking'],
             [
                 ['1 kg',  'kg', 1, 0.90, null,  500, false],
                 ['2 kg',  'kg', 2, 1.70, 1.80,  300, true],
                 ['25 kg bag','bag',25,18.00,null,80,false],
             ]],
            ['bariis-baasto-burr','Generic','Spaghetti Pasta','Baasto','pasta-spaghetti',false,'pack',
             ['baasto','pasta'],
             [
                 ['500 g pack','pack',1,0.90,null,400,true],
                 ['1 kg pack','pack',2,1.60,1.80,200,false],
             ]],
            ['bariis-baasto-burr','Generic','White Sugar','Sokor Cad','white-sugar',true,'kg',
             ['sokor','sugar','staple'],
             [
                 ['1 kg', 'kg', 1,  0.85, null,  400, false],
                 ['2 kg', 'kg', 2,  1.60, 1.70,  250, true],
                 ['50 kg bag','bag',50,38.00,null,30,false],
             ]],
            ['bariis-baasto-burr','Generic','Salt','Cusbo','salt',true,'kg',
             ['cusbo','salt'],
             [
                 ['500 g','g',500,0.30,null,600,false],
                 ['1 kg', 'kg',1,0.55,null,400,true],
             ]],

            // ── OIL & SPICES ──────────────────────────────────────────────────
            ['saliid-xawaash','Generic','Vegetable Cooking Oil','Saliid Khudaar','vegetable-cooking-oil',false,'L',
             ['saliid','oil','cooking'],
             [
                 ['1 L', 'L',1, 2.50,null,  300,false],
                 ['3 L', 'L',3, 6.80,7.50,  200,true],
                 ['5 L', 'L',5,10.50,null,  120,false],
             ]],
            ['saliid-xawaash','Generic','Xawaash Spice Mix','Xawaash','xawaash-spice-mix',false,'pack',
             ['xawaash','spice','blend'],
             [
                 ['100 g pack','pack',1,1.20,null,200,true],
                 ['250 g pack','pack',2,2.80,3.00,100,false],
             ]],
            ['saliid-xawaash','Generic','Turmeric (Huruud)','Huruud','turmeric-huruud',false,'pack',
             ['huruud','turmeric','spice'],
             [
                 ['100 g pack','pack',1,0.80,null,300,true],
                 ['250 g pack','pack',2,1.80,2.00,150,false],
             ]],
            ['saliid-xawaash','Generic','Cumin (Kummun)','Kummun','cumin-kummun',false,'pack',
             ['kummun','cumin','spice'],
             [
                 ['100 g pack','pack',1,0.90,null,200,true],
             ]],

            // ── FRUITS & VEGETABLES ───────────────────────────────────────────
            ['khudaar-furuutyo','Somali Fresh','Fresh Tomatoes','Yaanyo Cusub','fresh-tomatoes',true,'kg',
             ['yaanyo','tomatoes','fresh','vegetable'],
             [
                 ['500 g','g',500,0.70,null,150,false],
                 ['1 kg', 'kg',1,1.30,null, 80,true],
                 ['3 kg', 'kg',3,3.50,3.90, 40,false],
             ]],
            ['khudaar-furuutyo','Somali Fresh','Onion','Basal','onion-basal',true,'kg',
             ['basal','onion','vegetable'],
             [
                 ['1 kg','kg',1,0.70,null,200,true],
                 ['3 kg','kg',3,1.90,2.10,100,false],
             ]],
            ['khudaar-furuutyo','Somali Fresh','Potato','Baradhoofo','potato',true,'kg',
             ['baradhoofo','potato'],
             [
                 ['1 kg','kg',1,0.80,null,200,true],
                 ['5 kg','kg',5,3.80,4.00,80,false],
             ]],
            ['khudaar-furuutyo','Somali Fresh','Bananas','Muus','bananas-muus',true,'kg',
             ['muus','banana','fruit'],
             [
                 ['1 kg','kg',1,1.00,null,200,true],
                 ['3 kg','kg',3,2.70,3.00,80,false],
             ]],
            ['khudaar-furuutyo','Somali Fresh','Apple','Tufaax','apple-tufaax',true,'kg',
             ['tufaax','apple','fruit'],
             [
                 ['1 kg','kg',1,1.80,2.00,150,true],
                 ['3 kg','kg',3,5.00,5.40,60,false],
             ]],
            ['khudaar-furuutyo','Somali Fresh','Mango','Cambe','mango-cambe',true,'kg',
             ['cambe','mango','fruit'],
             [
                 ['1 kg','kg',1,1.50,null,120,true],
             ]],
            ['khudaar-furuutyo','Somali Fresh','Garlic','Toon','garlic-toon',false,'piece',
             ['toon','garlic','vegetable'],
             [
                 ['1 bulb','piece',1,0.30,null,400,false],
                 ['250 g bag','g',250,1.20,null,200,true],
             ]],
            ['khudaar-furuutyo','Somali Fresh','Fresh Ginger','Sinjibiil','fresh-ginger',true,'kg',
             ['sinjibiil','ginger'],
             [
                 ['250 g','g',250,0.60,null,200,false],
                 ['1 kg', 'kg',1,2.00,null,100,true],
             ]],
            ['khudaar-furuutyo','Somali Fresh','Cucumber','Qajaar','cucumber',true,'kg',
             ['qajaar','cucumber','vegetable'],
             [
                 ['1 kg','kg',1,1.00,null,120,true],
             ]],

            // ── MEAT & FISH ───────────────────────────────────────────────────
            ['hilib-kalluun','Somali Fresh','Whole Chicken','Digaag Oo Dhan','whole-chicken',true,'kg',
             ['digaag','chicken','meat'],
             [
                 ['~1.2 kg (whole)','kg',1.2,4.80,5.00,50,true],
             ]],
            ['hilib-kalluun','Somali Fresh','Goat Meat','Hilib Ari','goat-meat',true,'kg',
             ['hilib','ari','goat','meat'],
             [
                 ['500 g','g',500,3.50,null,60,false],
                 ['1 kg', 'kg',1,6.50,7.00,40,true],
             ]],
            ['hilib-kalluun','Somali Fresh','Beef (Hilib Lo)','Hilib Lo','beef-hilib-lo',true,'kg',
             ['hilib','lo','beef','meat'],
             [
                 ['500 g','g',500,3.00,null,60,false],
                 ['1 kg', 'kg',1,5.50,6.00,40,true],
             ]],
            ['hilib-kalluun','Somali Fresh','Fresh Fish (Kalluun)','Kalluun Cusub','fresh-fish',true,'kg',
             ['kalluun','fish','fresh'],
             [
                 ['500 g','g',500,2.50,null,80,false],
                 ['1 kg', 'kg',1,4.50,null,60,true],
             ]],

            // ── DAIRY & EGGS ──────────────────────────────────────────────────
            ['caano-ukun','Iftin Dairy','Fresh Milk','Caano Cusub','fresh-milk',false,'L',
             ['caano','milk','dairy','fresh'],
             [
                 ['500 ml','ml',500,0.90,null,200,false],
                 ['1 L',   'L', 1,  1.60,null,150,true],
             ]],
            ['caano-ukun','Dano','Milk Powder (Dano)','Caano Budo Dano','milk-powder-dano',false,'pack',
             ['caano','milk powder','budo','dano'],
             [
                 ['400 g tin','pack',1,3.80,4.00,150,false],
                 ['900 g tin','pack',2,8.00,8.50,80, true],
                 ['2.5 kg tin','pack',5,20.00,null,30,false],
             ]],
            ['caano-ukun','Nido','Milk Powder (Nido)','Caano Budo Nido','milk-powder-nido',false,'pack',
             ['caano','milk powder','budo','nido'],
             [
                 ['400 g tin','pack',1,4.20,4.50,100,true],
                 ['900 g tin','pack',2,8.80,9.00,60,false],
             ]],
            ['caano-ukun','Iftin Dairy','Fresh Eggs','Ukun Cusub','fresh-eggs',false,'dozen',
             ['ukun','eggs','fresh'],
             [
                 ['6 pcs',      'piece',6,  1.50,null, 200,false],
                 ['1 dozen',    'dozen',12, 2.80,3.00, 150,true],
                 ['Tray (30)',  'piece',30, 6.50,7.00, 80, false],
             ]],
            ['caano-ukun','Generic','Yoghurt','Yogurt','yoghurt',false,'pack',
             ['yogurt','dairy'],
             [
                 ['200 g','pack',1,0.80,null,200,true],
                 ['500 g','pack',2,1.80,2.00,100,false],
             ]],
            ['caano-ukun','Generic','Butter','Subag Cadey','butter',false,'pack',
             ['subag','butter','dairy'],
             [
                 ['250 g','pack',1,2.50,2.80,150,true],
                 ['500 g','pack',2,4.80,5.00,80,false],
             ]],

            // ── BAKERY ────────────────────────────────────────────────────────
            ['rooti-bakery','Generic','Sliced Bread','Rooti Gooyay','sliced-bread',false,'pack',
             ['rooti','bread','bakery'],
             [
                 ['400 g loaf','pack',1,1.20,null,200,true],
             ]],
            ['rooti-bakery','Generic','Doolshe (Biscuits)','Doolshe','doolshe-biscuits',false,'pack',
             ['doolshe','biscuit','bakery'],
             [
                 ['200 g pack','pack',1,0.80,0.90,300,true],
                 ['500 g pack','pack',2,1.80,2.00,150,false],
             ]],

            // ── BEVERAGES ────────────────────────────────────────────────────
            ['cabitaanno','Lipton','Black Tea Bags','Shaah Qudha','black-tea-bags-lipton',false,'pack',
             ['shaah','tea','lipton'],
             [
                 ['25 bags','pack',1,1.50,null,300,true],
                 ['100 bags','pack',4,5.50,6.00,150,false],
             ]],
            ['cabitaanno','Adani Chai','Adani Chai Spiced Tea','Shaah Adeni','adani-chai-tea',false,'pack',
             ['shaah','tea','adeni','spiced'],
             [
                 ['250 g pack','pack',1,2.50,2.80,200,true],
                 ['500 g pack','pack',2,4.80,5.00,100,false],
             ]],
            ['cabitaanno','Milo','Milo Chocolate Drink','Milo','milo-chocolate-drink',false,'pack',
             ['milo','chocolate','drink'],
             [
                 ['200 g tin','pack',1,2.80,null,200,true],
                 ['400 g tin','pack',2,5.20,5.50,100,false],
             ]],
            ['cabitaanno','Generic','Orange Juice','Casiir Liin Dirac','orange-juice',false,'L',
             ['juice','casiir','orange'],
             [
                 ['1 L carton','L',1,1.80,2.00,150,true],
             ]],
            ['cabitaanno','Generic','Coca-Cola','Kookakoola','coca-cola',false,'ml',
             ['cola','soft drink','cabitaan'],
             [
                 ['330 ml can','ml',330,0.60,null,400,false],
                 ['1.5 L bottle','L',1.5,1.40,1.50,200,true],
             ]],

            // ── WATER ─────────────────────────────────────────────────────────
            ['biyo','Candheeye','Drinking Water','Biyo Cabashada','drinking-water',false,'L',
             ['biyo','water','drinking'],
             [
                 ['600 ml bottle','ml',600,0.35,null,600,false],
                 ['1.5 L bottle', 'L', 1.5,0.65,null,400,true],
                 ['5 L bottle',   'L', 5,  1.80,2.00,150,false],
                 ['19 L jug',     'L', 19, 3.50,null,80,false],
             ]],
            ['biyo','Generic','Soda Water','Biyo Soda','soda-water',false,'ml',
             ['soda','water','biyo'],
             [
                 ['330 ml','ml',330,0.50,null,300,true],
             ]],

            // ── SNACKS ───────────────────────────────────────────────────────
            ['snacks-nacnac','Generic','Potato Chips','Chips Baradhoofo','potato-chips',false,'pack',
             ['chips','snack','nacnac'],
             [
                 ['50 g pack','pack',1,0.50,null,500,true],
                 ['150 g pack','pack',3,1.30,1.50,200,false],
             ]],
            ['snacks-nacnac','Generic','Peanuts (Digir)','Digir Shiilan','peanuts-digir',false,'pack',
             ['digir','peanuts','snack'],
             [
                 ['200 g pack','pack',1,0.90,null,300,true],
                 ['500 g pack','pack',2,2.00,2.20,150,false],
             ]],
            ['snacks-nacnac','Generic','Dates (Timir)','Timir','dates-timir',true,'kg',
             ['timir','dates','snack'],
             [
                 ['500 g','g',500,2.50,null,200,false],
                 ['1 kg', 'kg',1,4.80,5.00,100,true],
             ]],

            // ── CANNED & JARRED ───────────────────────────────────────────────
            ['qasacado','Generic','Canned Tomato Paste','Tomato Paste Qasacad','canned-tomato-paste',false,'pack',
             ['tomato','paste','canned','qasacad'],
             [
                 ['70 g sachet','pack',1,0.30,null,500,false],
                 ['400 g can',  'pack',4,0.80,0.90,300,true],
             ]],
            ['qasacado','Generic','Canned Tuna (Faro)','Faro Qasacad','canned-tuna-faro',false,'pack',
             ['faro','tuna','canned'],
             [
                 ['185 g can','pack',1,1.20,1.40,300,true],
             ]],
            ['qasacado','Generic','Canned Baked Beans','Digir Qasacad','canned-baked-beans',false,'pack',
             ['digir','beans','canned'],
             [
                 ['400 g can','pack',1,0.90,null,200,true],
             ]],
            ['qasacado','Generic','Honey (Malab)','Malab','honey-malab',false,'pack',
             ['malab','honey'],
             [
                 ['250 g jar','pack',1,3.50,3.80,150,true],
                 ['500 g jar','pack',2,6.50,7.00,80,false],
             ]],

            // ── HOUSEHOLD CLEANING ────────────────────────────────────────────
            ['nadaafadda-guriga','Generic','Dish Soap','Saabuun Saxanka','dish-soap',false,'ml',
             ['saabuun','dish','cleaning'],
             [
                 ['500 ml','ml',500,0.90,null,300,true],
                 ['1 L',   'L', 1,  1.60,1.80,150,false],
             ]],
            ['nadaafadda-guriga','Generic','Floor Cleaner','Bilic-nadiifin Daraawiish','floor-cleaner',false,'L',
             ['bilic','floor','cleaner'],
             [
                 ['1 L',  'L',1, 1.50,null,200,true],
                 ['3 L',  'L',3, 4.00,4.50,100,false],
             ]],
            ['nadaafadda-guriga','Generic','Toilet Paper','Warqadda Musqusha','toilet-paper',false,'pack',
             ['warqad','toilet','paper'],
             [
                 ['4 rolls pack','pack',4,1.20,null,300,true],
                 ['12 rolls pack','pack',12,3.20,3.50,150,false],
             ]],
            ['nadaafadda-guriga','Generic','Garbage Bags','Kiisaska Qashinka','garbage-bags',false,'pack',
             ['garbage','bags','kiis'],
             [
                 ['20 pcs pack','pack',20,0.80,null,400,true],
             ]],

            // ── LAUNDRY ───────────────────────────────────────────────────────
            ['dharka-dhaqid','Omo','Omo Washing Powder','Buluug Omo','omo-washing-powder',false,'kg',
             ['omo','washing','laundry','buluug'],
             [
                 ['500 g bag','bag',0.5,1.20,1.40,300,false],
                 ['2 kg bag', 'bag',2,  4.00,4.50,200,true],
                 ['5 kg bag', 'bag',5,  9.00,9.50,100,false],
             ]],
            ['dharka-dhaqid','Ariel','Ariel Washing Powder','Buluug Ariel','ariel-washing-powder',false,'kg',
             ['ariel','washing','laundry'],
             [
                 ['500 g bag','bag',0.5,1.40,null,200,false],
                 ['2 kg bag', 'bag',2,  4.50,5.00,120,true],
             ]],
            ['dharka-dhaqid','Downy','Downy Fabric Softener','Downy Laalin Dharka','downy-fabric-softener',false,'L',
             ['downy','softener','fabric'],
             [
                 ['900 ml','ml',900,2.50,2.80,200,true],
             ]],

            // ── PERSONAL CARE ─────────────────────────────────────────────────
            ['daryeelka-shakhsiga','Dove','Dove Soap Bar','Saabuun Dove','dove-soap-bar',false,'pack',
             ['saabuun','soap','dove'],
             [
                 ['90 g bar','pack',1,0.80,null,400,false],
                 ['3-pack',  'pack',3,2.20,2.40,200,true],
             ]],
            ['daryeelka-shakhsiga','Dove','Dove Shampoo','Shampoo Dove','dove-shampoo',false,'ml',
             ['shampoo','hair','dove'],
             [
                 ['200 ml','ml',200,2.50,2.80,200,true],
                 ['400 ml','ml',400,4.50,5.00,100,false],
             ]],
            ['daryeelka-shakhsiga','Generic','Toothpaste','Furusheet Ilkaha','toothpaste',false,'pack',
             ['toothpaste','furusheet','oral'],
             [
                 ['100 ml tube','ml',100,0.90,null,300,true],
                 ['250 ml tube','ml',250,1.80,2.00,150,false],
             ]],
            ['daryeelka-shakhsiga','Generic','Deodorant','Lidid-Dufan','deodorant',false,'ml',
             ['deodorant','personal care'],
             [
                 ['150 ml spray','ml',150,1.80,2.00,200,true],
             ]],

            // ── BABY CARE ─────────────────────────────────────────────────────
            ['ilmaha','Pampers','Pampers Diapers Size 3','Xafaayad Pampers Tirada 3','pampers-size-3',false,'pack',
             ['pampers','diapers','xafaayad','baby','ilmo'],
             [
                 ['30 pcs pack','pack',30,8.50,9.00,100,true],
                 ['62 pcs pack','pack',62,16.00,17.00,50,false],
             ]],
            ['ilmaha','Pampers','Pampers Diapers Size 4','Xafaayad Pampers Tirada 4','pampers-size-4',false,'pack',
             ['pampers','diapers','xafaayad','baby','ilmo'],
             [
                 ['28 pcs pack','pack',28,8.80,9.50,100,true],
                 ['54 pcs pack','pack',54,16.50,17.50,50,false],
             ]],
            ['ilmaha','Pampers','Pampers Diapers Size 5','Xafaayad Pampers Tirada 5','pampers-size-5',false,'pack',
             ['pampers','diapers','xafaayad','baby','ilmo'],
             [
                 ['26 pcs pack','pack',26,9.00,9.80,80,true],
                 ['48 pcs pack','pack',48,17.00,18.00,40,false],
             ]],
            ['ilmaha','Generic','Baby Wipes','Maasadaha Ilmaha','baby-wipes',false,'pack',
             ['baby','wipes','ilmo'],
             [
                 ['80 pcs pack','pack',80,1.80,2.00,200,true],
             ]],
            ['ilmaha','Generic','Baby Powder','Budo Ilmaha','baby-powder',false,'pack',
             ['baby','powder','budo','ilmo'],
             [
                 ['100 g pack','pack',1,1.50,null,200,true],
                 ['400 g pack','pack',4,3.50,3.80,100,false],
             ]],
        ];

        // ── Insert products + variants ─────────────────────────────────────────
        foreach ($catalog as [$catSlug, $brandName, $name, $nameSo, $slug, $isWeightBased, $baseUnit, $tags, $variants]) {

            $catIdVal    = $cid($catSlug);
            $brandIdVal  = $bid($brandName);
            $unitIdVal   = $uid($baseUnit);

            if (!$catIdVal || !$unitIdVal) {
                $this->command->warn("Skip $slug — missing cat/unit");
                continue;
            }

            DB::table('egrocery_products')->updateOrInsert(['slug' => $slug], [
                'category_id'     => $catIdVal,
                'brand_id'        => $brandIdVal,
                'name'            => $name,
                'name_so'         => $nameSo,
                'slug'            => $slug,
                'description'     => null,
                'images'          => json_encode([]),
                'base_unit_id'    => $unitIdVal,
                'is_weight_based' => $isWeightBased,
                'tags'            => json_encode($tags),
                'barcode'         => null,
                'is_active'       => true,
                'is_featured'     => in_array($slug, [
                    'basmati-rice','vegetable-cooking-oil','fresh-eggs',
                    'drinking-water','omo-washing-powder','pampers-size-3',
                ]),
                'avg_rating'      => round(mt_rand(38, 50) / 10, 1),
                'orders_count'    => mt_rand(20, 500),
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $productId = DB::table('egrocery_products')->where('slug', $slug)->value('id');

            foreach ($variants as $i => [$label, $unitName, $qty, $price, $compare, $stock, $isDefault]) {
                $varUnitId = $uid($unitName);
                $sku = strtoupper(Str::slug($slug)) . '-V' . ($i + 1);
                DB::table('egrocery_product_variants')->updateOrInsert(
                    ['product_id' => $productId, 'label' => $label],
                    [
                        'product_id'          => $productId,
                        'label'               => $label,
                        'unit_id'             => $varUnitId,
                        'unit_qty'            => $qty,
                        'price'               => $price,
                        'compare_price'       => $compare,
                        'cost'                => null,
                        'sku'                 => $sku,
                        'stock_qty'           => $stock,
                        'low_stock_threshold' => $isWeightBased ? 5 : 10,
                        'is_default'          => $isDefault,
                        'sort_order'          => $i,
                        'is_active'           => true,
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ]
                );
            }
        }

        // ── 2 Flash Deals ─────────────────────────────────────────────────────
        $flashSection = DB::table('egrocery_sections')->where('type','flash_deal')->first();
        if ($flashSection) {
            // Flash deal 1: Basmati Rice 5kg
            $v1 = DB::table('egrocery_product_variants')
                ->join('egrocery_products','egrocery_products.id','=','egrocery_product_variants.product_id')
                ->where('egrocery_products.slug','basmati-rice')
                ->where('egrocery_product_variants.is_default',true)
                ->value('egrocery_product_variants.id');

            // Flash deal 2: Omo 2kg
            $v2 = DB::table('egrocery_product_variants')
                ->join('egrocery_products','egrocery_products.id','=','egrocery_product_variants.product_id')
                ->where('egrocery_products.slug','omo-washing-powder')
                ->where('egrocery_product_variants.label','2 kg bag')
                ->value('egrocery_product_variants.id');

            if ($v1) {
                DB::table('egrocery_flash_deals')->updateOrInsert(
                    ['section_id' => $flashSection->id, 'variant_id' => $v1],
                    ['section_id' => $flashSection->id, 'variant_id' => $v1,
                     'deal_price' => 7.00, 'qty_limit' => 50, 'qty_sold' => 3,
                     'created_at' => $now, 'updated_at' => $now]
                );
            }
            if ($v2) {
                DB::table('egrocery_flash_deals')->updateOrInsert(
                    ['section_id' => $flashSection->id, 'variant_id' => $v2],
                    ['section_id' => $flashSection->id, 'variant_id' => $v2,
                     'deal_price' => 3.20, 'qty_limit' => 30, 'qty_sold' => 7,
                     'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        // ── Section products (Best Sellers: 8 products) ───────────────────────
        $bestSellers = DB::table('egrocery_sections')->where('type','best_sellers')->first();
        if ($bestSellers) {
            $topSlugs = ['basmati-rice','drinking-water','fresh-eggs','vegetable-cooking-oil',
                         'white-sugar','wheat-flour','omo-washing-powder','fresh-tomatoes'];
            foreach ($topSlugs as $i => $s) {
                $pid = DB::table('egrocery_products')->where('slug',$s)->value('id');
                if ($pid) {
                    DB::table('egrocery_section_products')->updateOrInsert(
                        ['section_id' => $bestSellers->id, 'product_id' => $pid],
                        ['section_id' => $bestSellers->id, 'product_id' => $pid, 'sort_order' => $i]
                    );
                }
            }
        }

        $total    = DB::table('egrocery_products')->count();
        $variants = DB::table('egrocery_product_variants')->count();
        $cats     = DB::table('egrocery_categories')->count();
        $this->command->info("✅ Catalog seeded: {$cats} categories, {$total} products, {$variants} variants.");
    }
}
