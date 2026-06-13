<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            ['from' => 'USD', 'to' => 'SOS', 'rate' => 571.000000, 'fee' => 1.5],
            ['from' => 'SOS', 'to' => 'USD', 'rate' => 0.001750,  'fee' => 1.5],
            ['from' => 'USD', 'to' => 'EUR', 'rate' => 0.920000,  'fee' => 2.0],
            ['from' => 'EUR', 'to' => 'USD', 'rate' => 1.087000,  'fee' => 2.0],
            ['from' => 'USD', 'to' => 'SAR', 'rate' => 3.750000,  'fee' => 1.5],
            ['from' => 'SAR', 'to' => 'USD', 'rate' => 0.267000,  'fee' => 1.5],
            ['from' => 'USD', 'to' => 'AED', 'rate' => 3.670000,  'fee' => 1.5],
            ['from' => 'USD', 'to' => 'GBP', 'rate' => 0.790000,  'fee' => 2.0],
            ['from' => 'GBP', 'to' => 'USD', 'rate' => 1.265000,  'fee' => 2.0],
            ['from' => 'USD', 'to' => 'KES', 'rate' => 130.000000,'fee' => 2.0],
            ['from' => 'ETB', 'to' => 'USD', 'rate' => 0.018000,  'fee' => 2.0],
            ['from' => 'USD', 'to' => 'ETB', 'rate' => 55.000000, 'fee' => 2.0],
        ];

        foreach ($rates as $r) {
            DB::table('exchange_rates')->updateOrInsert(
                ['from_wallet' => $r['from'], 'to_wallet' => $r['to']],
                [
                    'from_wallet' => $r['from'],
                    'to_wallet'   => $r['to'],
                    'rate'        => $r['rate'],
                    'fee_type'    => 'percentage',
                    'fee_value'   => $r['fee'],
                    'is_active'   => true,
                    'updated_at'  => now(),
                ]
            );
        }
    }
}
