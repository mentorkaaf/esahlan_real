<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EGroceryOrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Get real variant IDs and prices from the catalog
        $variants = DB::table('egrocery_product_variants as v')
            ->join('egrocery_products as p', 'p.id', '=', 'v.product_id')
            ->whereNotNull('v.price')
            ->where('v.price', '>', 0)
            ->select('v.id', 'v.label', 'v.price', 'p.name as product_name', 'p.id as product_id')
            ->limit(40)
            ->get();

        if ($variants->isEmpty()) {
            $this->command->warn('No variants found — run EGroceryCatalogSeeder first.');
            return;
        }

        // Get a real user to assign orders to
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $userId = $adminRoleId
            ? (DB::table('users')->where('role_id', '!=', $adminRoleId)->value('id') ?? DB::table('users')->value('id'))
            : DB::table('users')->value('id');

        if (!$userId) {
            $this->command->warn('No users found — create a user first.');
            return;
        }

        $statuses = [
            'pending',
            'confirmed',
            'picking',
            'ready',
            'out_for_delivery',
            'delivered',
            'delivered',
            'cancelled',
        ];

        $paymentMethods = ['cash', 'evc', 'cash', 'cash', 'evc'];

        DB::table('egrocery_orders')->whereRaw("order_no LIKE 'DEMO-%'")->delete();

        foreach ($statuses as $i => $status) {
            $orderNo = 'DEMO-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $method  = $paymentMethods[$i % count($paymentMethods)];
            $payStatus = in_array($status, ['delivered', 'out_for_delivery']) && $method === 'waafipay'
                ? 'paid' : ($status === 'cancelled' ? 'refunded' : 'unpaid');
            $createdAt = Carbon::now()->subHours(rand(1, 72));
            $updatedAt = $createdAt->copy()->addMinutes(rand(5, 120));

            // Pick 2–4 random variants
            $picked = $variants->shuffle()->take(rand(2, 4));
            $subtotal = 0;
            $items = [];
            foreach ($picked as $v) {
                $qty = rand(1, 3);
                $price = (float)$v->price;
                $line = round($qty * $price, 2);
                $subtotal += $line;
                $items[] = [
                    'variant_id'             => $v->id,
                    'name_snapshot'          => $v->product_name,
                    'unit_label_snapshot'    => $v->label,
                    'unit_price_snapshot'    => $price,
                    'qty'                    => $qty,
                    'line_total'             => $line,
                    'picked_qty'             => in_array($status, ['ready','out_for_delivery','delivered']) ? $qty : null,
                    'created_at'             => $createdAt,
                    'updated_at'             => $updatedAt,
                ];
            }
            $subtotal = round($subtotal, 2);
            $deliveryFee = 2.00;
            $discount = ($i % 3 === 0) ? 1.00 : 0.00;
            $total = round($subtotal + $deliveryFee - $discount, 2);

            $orderId = DB::table('egrocery_orders')->insertGetId([
                'order_no'       => $orderNo,
                'user_id'        => $userId,
                'status'         => $status,
                'payment_method' => $method,
                'payment_status' => $payStatus,
                'subtotal'       => $subtotal,
                'delivery_fee'   => $deliveryFee,
                'discount'       => $discount,
                'total'          => $total,
                'customer_note'  => $i === 1 ? 'Please leave at door.' : null,
                'cancelled_reason' => $status === 'cancelled' ? 'Customer changed mind' : null,
                'created_at'     => $createdAt,
                'updated_at'     => $updatedAt,
            ]);

            foreach ($items as &$item) {
                $item['order_id'] = $orderId;
            }
            DB::table('egrocery_order_items')->insert($items);

            $this->command->line("  ✓ {$orderNo} [{$status}] — \${$total}");
        }

        $this->command->info('8 demo orders seeded successfully.');
    }
}
