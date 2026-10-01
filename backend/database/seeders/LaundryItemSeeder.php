<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LaundryItemSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('laundry_items')->truncate();

        $items = [
            // ─── Clean & Press ───────────────────────────────────────────
            // Ladies Group
            ['Clean & Press', 'Ladies Group', 'Bijaamo',                        2.00, 3],
            ['Clean & Press', 'Ladies Group', 'Cabayaa-Abaya',                  2.00, 3],
            ['Clean & Press', 'Ladies Group', 'Dirac Baati',                    1.00, 3],
            ['Clean & Press', 'Ladies Group', 'Garbasaar',                      0.50, 3],
            ['Clean & Press', 'Ladies Group', 'Goono',                          0.75, 3],
            ['Clean & Press', 'Ladies Group', 'Gorgorad',                       1.00, 3],
            ['Clean & Press', 'Ladies Group', 'Indha-shareer',                  0.50, 3],
            ['Clean & Press', 'Ladies Group', 'Iskudays',                       2.00, 3],
            ['Clean & Press', 'Ladies Group', 'Istiriij',                       1.00, 3],
            ['Clean & Press', 'Ladies Group', 'Qamaar-Headscarf',               0.50, 3],
            ['Clean & Press', 'Ladies Group', 'Rajabeeto',                      0.50, 3],
            ['Clean & Press', 'Ladies Group', 'Saako',                          1.50, 3],
            ['Clean & Press', 'Ladies Group', 'Saako Aroos',                    4.00, 3],
            ['Clean & Press', 'Ladies Group', 'Taash',                         10.00, 3],
            ['Clean & Press', 'Ladies Group', 'Xijaab',                         1.50, 3],
            // Men
            ['Clean & Press', 'Men', 'Cimaamad-Shemagh',                        1.00, 3],
            ['Clean & Press', 'Men', 'Daba Gaab-Short trouser',                  1.00, 3],
            ['Clean & Press', 'Men', 'Funaanad Caadi-T-shirt with collar',       1.00, 3],
            ['Clean & Press', 'Men', 'Funaanad Xarago-T-shirt without collar',   1.50, 3],
            ['Clean & Press', 'Men', 'Jiinis-Jeans',                             2.00, 3],
            ['Clean & Press', 'Men', 'Koofi-Hat',                                1.00, 3],
            ['Clean & Press', 'Men', 'Qamiis & Surwaal Baakistaani',             2.00, 3],
            ['Clean & Press', 'Men', 'Qamiis Baakistaani',                       1.50, 3],
            ['Clean & Press', 'Men', 'Qamiis-Thobe',                             2.00, 3],
            ['Clean & Press', 'Men', 'Shaati-Shirt',                             1.50, 3],
            ['Clean & Press', 'Men', 'Sigsaan-Socks',                            0.50, 3],
            ['Clean & Press', 'Men', 'Surwaal-Trouser',                          1.50, 3],
            // Traditional
            ['Clean & Press', 'Traditional', 'Futashaari-Local Suit',            3.00, 3],
            ['Clean & Press', 'Traditional', 'Go/Shaal',                         2.00, 3],
            ['Clean & Press', 'Traditional', 'Macawiis-Sarong',                  1.50, 3],
            ['Clean & Press', 'Traditional', 'Shaati Futashaari',                1.50, 3],
            // Suit Group
            ['Clean & Press', 'Suit Group', 'Futashaari Classic',                3.00, 3],
            ['Clean & Press', 'Suit Group', 'Garabaati-Tie',                     0.50, 3],
            ['Clean & Press', 'Suit Group', 'Jaakad caadi-Jacket',               2.00, 3],
            ['Clean & Press', 'Suit Group', 'Jaakad Leather-Leather jacket',     4.00, 3],
            ['Clean & Press', 'Suit Group', 'Jaakad Suud-Suit Jacket',           4.00, 3],
            ['Clean & Press', 'Suit Group', 'Mis-Jaako-Short blazer',            1.00, 3],
            ['Clean & Press', 'Suit Group', 'Suud 2Pc-Suit 2Pc',                5.00, 3],
            ['Clean & Press', 'Suit Group', 'Suud 3Pc-Suit 3Pc',                7.00, 3],
            // Underwear Group
            ['Clean & Press', 'Underwear Group', 'Buumo-Underwear',              0.50, 3],
            ['Clean & Press', 'Underwear Group', 'Funaanad Hoose-Undershirt',    0.50, 3],
            ['Clean & Press', 'Underwear Group', 'Garan-Undershirt',             0.50, 3],
            ['Clean & Press', 'Underwear Group', 'Nigis-Underwear',              0.50, 3],
            ['Clean & Press', 'Underwear Group', 'Surwaal Hoose-Underwear',      0.50, 3],
            // Sportswear
            ['Clean & Press', 'Sportswear', 'Funaanad Sports-Sports T-shirt',   1.00, 3],
            ['Clean & Press', 'Sportswear', 'Isku-joog Sports-Sports Dress',    3.00, 3],
            ['Clean & Press', 'Sportswear', 'Istariij Sports',                  0.50, 3],
            ['Clean & Press', 'Sportswear', 'Jaakad Sports-Sports Jacket',      1.00, 3],
            ['Clean & Press', 'Sportswear', 'Surwaal Sports-Sports Trouser',    1.50, 3],
            // Dress Group
            ['Clean & Press', 'Dress Group', 'African Dress',                   1.50, 3],
            ['Clean & Press', 'Dress Group', 'Aviation Dress',                  3.00, 3],
            ['Clean & Press', 'Dress Group', 'Isku-Joga Xirfadlayasha',         3.00, 3],
            ['Clean & Press', 'Dress Group', 'Isku-Joog Waxbarasho-Uniform',    3.00, 3],
            ['Clean & Press', 'Dress Group', 'Ixraam',                          2.00, 3],
            ['Clean & Press', 'Dress Group', 'Jaakada Dhaqaatiirta-Doctor Jacket', 2.00, 3],
            ['Clean & Press', 'Dress Group', 'Military Dress',                  6.00, 3],
            ['Clean & Press', 'Dress Group', 'Police Dress',                    3.00, 3],
            ['Clean & Press', 'Dress Group', 'Traffic Dress',                   3.00, 3],
            // Bags Group
            ['Clean & Press', 'Bags Group', 'Boorso Ciidan-Army Bag',           2.00, 3],
            ['Clean & Press', 'Bags Group', 'Boorso Laptop-Laptop bag',         1.50, 3],
            // Shoes Group
            ['Clean & Press', 'Shoes Group', 'Kabo Ciidan',                     3.00, 3],
            ['Clean & Press', 'Shoes Group', 'Kabo Dumar',                      2.00, 3],
            ['Clean & Press', 'Shoes Group', 'Kabo Sneaker',                    2.00, 3],
            ['Clean & Press', 'Shoes Group', 'Kabo Sports',                     2.00, 3],
            ['Clean & Press', 'Shoes Group', 'Kabo Suud',                       2.00, 3],
            ['Clean & Press', 'Shoes Group', 'Saandal',                         1.00, 3],

            // ─── Press Only ───────────────────────────────────────────────
            // Ladies Group
            ['Press Only', 'Ladies Group', 'Qamaar-Headscarf',                  0.25, 1],
            ['Press Only', 'Ladies Group', 'Dirac Baati',                        0.50, 1],
            ['Press Only', 'Ladies Group', 'Dirac Xariir',                       0.75, 1],
            ['Press Only', 'Ladies Group', 'Indha-Shareer',                      0.25, 1],
            ['Press Only', 'Ladies Group', 'Iskudays',                           1.00, 1],
            ['Press Only', 'Ladies Group', 'Istiriij',                           0.50, 1],
            ['Press Only', 'Ladies Group', 'Saako',                              0.75, 1],
            ['Press Only', 'Ladies Group', 'Saako Aroos',                        2.00, 1],
            ['Press Only', 'Ladies Group', 'Taash',                              5.00, 1],
            ['Press Only', 'Ladies Group', 'Xijaab',                             0.75, 1],
            // Men
            ['Press Only', 'Men', 'Cimaamad-Shemagh',                           0.25, 1],
            ['Press Only', 'Men', 'Daba Gaab-Short trouser',                     0.25, 1],
            ['Press Only', 'Men', 'Funaanad Caadi-T-shirt with collar',          0.50, 1],
            ['Press Only', 'Men', 'Funaanad Xarago-T-shirt without collar',      0.75, 1],
            ['Press Only', 'Men', 'Jiinis-Jeans',                                1.00, 1],
            ['Press Only', 'Men', 'Qamiis-Thobe',                                1.00, 1],
            ['Press Only', 'Men', 'Shaati-Shirt',                                0.75, 1],
            ['Press Only', 'Men', 'Surwaal-Trouser',                             0.75, 1],
            // Traditional
            ['Press Only', 'Traditional', 'Futashaari-Local Suit',               1.50, 1],
            ['Press Only', 'Traditional', 'Go/Shaal',                            1.00, 1],
            ['Press Only', 'Traditional', 'Macawiis-Sarong',                     0.75, 1],
            ['Press Only', 'Traditional', 'Shaati Futashaari',                   0.75, 1],
            // Suit Group
            ['Press Only', 'Suit Group', 'Futashaari Classic',                   1.50, 1],
            ['Press Only', 'Suit Group', 'Garabaati-Tie',                        0.25, 1],
            ['Press Only', 'Suit Group', 'Jaakad caadi-Jacket',                  1.00, 1],
            ['Press Only', 'Suit Group', 'Jaakad Leather-Leather jacket',        4.00, 1],
            ['Press Only', 'Suit Group', 'Jaakad Suud-Suit Jacket',              2.00, 1],
            ['Press Only', 'Suit Group', 'Mis-Jaako-Short blazer',               0.50, 1],
            ['Press Only', 'Suit Group', 'Suud 2Pc-Suit 2Pc',                   3.00, 1],
            ['Press Only', 'Suit Group', 'Suud 3Pc-Suit 3Pc',                   3.50, 1],
            // Underwear Group
            ['Press Only', 'Underwear Group', 'Buumo-Underwear',                 0.25, 1],
            ['Press Only', 'Underwear Group', 'Funaanad Hoose-Undershirt',       0.25, 1],
            ['Press Only', 'Underwear Group', 'Garan-Undershirt',                0.25, 1],
            ['Press Only', 'Underwear Group', 'Nigis-Underwear',                 0.25, 1],
            ['Press Only', 'Underwear Group', 'Surwaal Hoose-Underwear',         0.25, 1],
            // Sportswear
            ['Press Only', 'Sportswear', 'Funaanad Sports-Sports T-shirt',       0.50, 1],
            ['Press Only', 'Sportswear', 'Istiriij Sports',                      0.25, 1],
            ['Press Only', 'Sportswear', 'Isku-joog Sports-Sports Dress',        1.50, 1],
            ['Press Only', 'Sportswear', 'Surwaal Sports-Sports Trouser',        0.75, 1],
            // Dress Group
            ['Press Only', 'Dress Group', 'African Dress',                       1.50, 1],
            ['Press Only', 'Dress Group', 'Isku-Joga Xirfadlayasha',             1.50, 1],
            ['Press Only', 'Dress Group', 'Isku-Joog Waxbarasho-Uniform',        1.50, 1],
            ['Press Only', 'Dress Group', 'Jaakada Dhaqaatiirta-Doctor Jacket',  1.00, 1],
            ['Press Only', 'Dress Group', 'Military Dress',                      3.00, 1],
            ['Press Only', 'Dress Group', 'Police Dress',                        1.50, 1],
            ['Press Only', 'Dress Group', 'Traffic Dress',                       1.50, 1],

            // ─── Wash & Fold ──────────────────────────────────────────────
            // Ladies Group
            ['Wash & Fold', 'Ladies Group', 'Gorgorad',                          0.50, 1],
            ['Wash & Fold', 'Ladies Group', 'Indha-shareer',                     0.25, 1],
            ['Wash & Fold', 'Ladies Group', 'Istiriij',                          0.50, 1],
            ['Wash & Fold', 'Ladies Group', 'Qamaar-Headscarf',                  0.25, 1],
            ['Wash & Fold', 'Ladies Group', 'Rajabeeto',                         0.25, 1],
            // Men
            ['Wash & Fold', 'Men', 'Koofi-Hat',                                  0.50, 1],
            ['Wash & Fold', 'Men', 'Sigsaan-Socks',                              0.25, 1],
            // Underwear Group
            ['Wash & Fold', 'Underwear Group', 'Buumo-Underwear',                0.25, 1],
            ['Wash & Fold', 'Underwear Group', 'Funaanad Hoose-Undershirt',      0.25, 1],
            ['Wash & Fold', 'Underwear Group', 'Garan-Undershirt',               0.25, 1],
            ['Wash & Fold', 'Underwear Group', 'Nigis-Underwear',                0.25, 1],
            ['Wash & Fold', 'Underwear Group', 'Surwaal Hoose-Underwear',        0.25, 1],
            // Bath
            ['Wash & Fold', 'Bath', "Go' Sariir Hal Nafar",                      1.00, 1],
            ['Wash & Fold', 'Bath', 'Go/Shaal',                                  1.00, 1],
            ['Wash & Fold', 'Bath', "Go' Sariir Laba Nafar",                     1.50, 1],
            ['Wash & Fold', 'Bath', 'Ixraam',                                    1.00, 1],
            ['Wash & Fold', 'Bath', 'Shukumaan Weyn-Large towel',                2.00, 1],
            ['Wash & Fold', 'Bath', 'Shukumaan Yar',                             0.75, 1],

            // ─── Bed & Bath ───────────────────────────────────────────────
            // Bath
            ['Bed & Bath', 'Bath', 'Shukumaan Weyn-Large towel',                 3.00, 1],
            ['Bed & Bath', 'Bath', 'Shukumaan Yar',                              1.50, 1],
            // Bed
            ['Bed & Bath', 'Bed', 'Buste Hal Nafar',                             4.00, 1],
            ['Bed & Bath', 'Bed', 'Buste Laba Nafar',                            6.00, 1],
            ['Bed & Bath', 'Bed', 'Foodare Kubeerto',                            3.00, 1],
            ['Bed & Bath', 'Bed', 'Foodare-Pillowcase',                          0.50, 1],
            ['Bed & Bath', 'Bed', 'Go Sariir Hal Nafar',                         1.50, 1],
            ['Bed & Bath', 'Bed', "Go' Sariir Laba Nafar",                       2.50, 1],
            ['Bed & Bath', 'Bed', 'Kubeerto Weyn',                               7.00, 1],
            ['Bed & Bath', 'Bed', 'Kubeerto Yar',                                5.00, 1],
            // Home
            ['Bed & Bath', 'Home', 'Caga-saar Weyn',                             5.00, 1],
            ['Bed & Bath', 'Home', 'Caga-saar Yar',                              3.00, 1],
            ['Bed & Bath', 'Home', 'Daah-Curtain',                               1.00, 1],
            ['Bed & Bath', 'Home', 'Foodaraha Kuraasta',                        10.00, 1],
            ['Bed & Bath', 'Home', 'joodari',                                   20.00, 1],
            ['Bed & Bath', 'Home', 'Maro Fadhi (Full)',                          15.00, 1],
            ['Bed & Bath', 'Home', 'Maro Fadhi Haff',                           10.00, 1],
            ['Bed & Bath', 'Home', 'Roog',                                        3.00, 1],
            ['Bed & Bath', 'Home', 'Sali Salaad Weyn',                           3.00, 1],
            ['Bed & Bath', 'Home', 'Sali Salaad Yar',                            2.00, 1],
            // Guest
            ['Bed & Bath', 'Guest', 'Istiraasho Weyn',                           0.50, 1],
            ['Bed & Bath', 'Guest', 'Istiraasho Yar',                            0.25, 1],
            ['Bed & Bath', 'Guest', 'Maro Miis Weyn',                            3.00, 1],
            ['Bed & Bath', 'Guest', 'Maro Miis Yar',                             1.50, 1],
        ];

        $now  = now();
        $rows = [];
        foreach ($items as $i => [$main, $sub, $name, $price, $days]) {
            $rows[] = [
                'name'          => $name,
                'main_category' => $main,
                'sub_category'  => $sub,
                'normal_price'  => $price,
                'express_price' => 0,
                'normal_days'   => $days,
                'express_hours' => 24,
                'is_active'     => true,
                'sort_order'    => $i + 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        DB::table('laundry_items')->insert($rows);
        $this->command->info('LaundryItemSeeder: ' . count($rows) . ' items inserted.');
    }
}
