<?php

namespace Database\Seeders\EWholesale;

use App\Models\EWholesale\{
    EWSupplier, EWBuyer, EWProduct, EWProductVariant,
    EWOrder, EWOrderItem, EWOrderPayment, EWShipment, EWDispute, EWRfq, EWRfqQuote, EWSetting
};
use App\Services\EWholesale\PricingService;
use Illuminate\Database\Seeder;

class EWholesaleOrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding eWholesale demo orders, RFQs, disputes...');

        $pricing = app(PricingService::class);

        $suppliers = EWSupplier::all();
        $buyers    = EWBuyer::with('user')->get();
        $products  = EWProduct::with(['variants','priceTiers'])->where('status','active')->get();

        if ($suppliers->isEmpty() || $buyers->isEmpty() || $products->isEmpty()) {
            $this->command->error('Run EWholesaleSeeder first!');
            return;
        }

        // ── Seed default settings ─────────────────────────────────────────
        foreach (EWSetting::defaults() as $d) {
            EWSetting::firstOrCreate(['key' => $d['key']], $d);
        }

        // ── Helper ────────────────────────────────────────────────────────
        $makeOrder = function (EWBuyer $buyer, EWSupplier $supplier, array $lines, array $overrides = []) use ($pricing): EWOrder {
            $subtotal = 0;
            $itemsData = [];
            foreach ($lines as $line) {
                $product = $line['product'];
                $variant = $product->variants->first();
                $qty     = $line['qty'];
                $resolved = $pricing->resolve($product, $variant, $qty, $buyer);
                $unitPrice= $resolved['unit_price'];
                $lineTotal= round($qty * $unitPrice, 2);
                $subtotal += $lineTotal;
                $itemsData[] = [
                    'product'          => $product,
                    'variant'          => $variant,
                    'qty'              => $qty,
                    'unit_price_snapshot' => $unitPrice,
                    'line_total'       => $lineTotal,
                ];
            }
            $deliveryFee = $subtotal >= 500 ? 0 : 3.00;
            $platformFee = round($subtotal * 0.025, 2);
            $total       = $subtotal + $deliveryFee + $platformFee;

            $order = EWOrder::create(array_merge([
                'order_no'     => EWOrder::generateOrderNo(),
                'buyer_id'     => $buyer->id,
                'supplier_id'  => $supplier->id,
                'source'       => 'cart',
                'status'       => 'pending_confirmation',
                'payment_plan' => 'prepaid',
                'subtotal'     => $subtotal,
                'delivery_fee' => $deliveryFee,
                'platform_fee' => $platformFee,
                'total'        => $total,
                'paid_total'   => 0,
            ], $overrides));

            foreach ($itemsData as $it) {
                EWOrderItem::create([
                    'order_id'            => $order->id,
                    'product_id'          => $it['product']->id,
                    'variant_id'          => $it['variant']?->id,
                    'name_snapshot'       => $it['product']->name,
                    'unit_snapshot'       => $it['product']->unit,
                    'qty'                 => $it['qty'],
                    'unit_price_snapshot' => $it['unit_price_snapshot'],
                    'line_total'          => $it['line_total'],
                ]);
            }

            return $order;
        };

        $buyer1 = $buyers->first();
        $buyer2 = $buyers->skip(1)->first() ?? $buyers->first();
        $buyer3 = $buyers->skip(2)->first() ?? $buyers->first();

        $sup1  = $suppliers->first();
        $sup2  = $suppliers->skip(1)->first() ?? $suppliers->first();

        $prod1 = $products->first();
        $prod2 = $products->skip(1)->first() ?? $products->first();
        $prod3 = $products->skip(2)->first() ?? $products->first();
        $prod4 = $products->skip(3)->first() ?? $products->first();

        // ── Order 1: completed ────────────────────────────────────────────
        $o1 = $makeOrder($buyer1, $sup1, [
            ['product'=>$prod1,'qty'=>50],
            ['product'=>$prod2,'qty'=>20],
        ], ['status'=>'completed','confirmed_at'=>now()->subDays(10),'completed_at'=>now()->subDays(2)]);
        EWOrderPayment::create(['order_id'=>$o1->id,'type'=>'full','method'=>'evc','amount'=>$o1->total,'status'=>'confirmed','paid_at'=>now()->subDays(9),'recorded_by'=>1]);
        $o1->update(['paid_total'=>$o1->total]);
        $shipNo = 'EWS-'.strtoupper(substr(md5('ship1'),0,6));
        EWShipment::create(['order_id'=>$o1->id,'shipment_no'=>$shipNo,'items'=>$o1->items->map(fn($i)=>['item_id'=>$i->id,'qty'=>$i->qty])->toArray(),'status'=>'delivered','dispatched_at'=>now()->subDays(7),'delivered_at'=>now()->subDays(5)]);

        // ── Order 2: delivered ────────────────────────────────────────────
        $o2 = $makeOrder($buyer2, $sup1, [['product'=>$prod3,'qty'=>30]], ['status'=>'delivered','confirmed_at'=>now()->subDays(6)]);
        EWOrderPayment::create(['order_id'=>$o2->id,'type'=>'full','method'=>'cash','amount'=>$o2->total,'status'=>'confirmed','paid_at'=>now()->subDays(5),'recorded_by'=>1]);
        $o2->update(['paid_total'=>$o2->total]);

        // ── Order 3: deposit-plan (two payments) + partial shipment ───────
        $o3 = $makeOrder($buyer3, $sup2, [
            ['product'=>$prod1,'qty'=>100],
            ['product'=>$prod4,'qty'=>50],
        ], [
            'status'          => 'partially_shipped',
            'payment_plan'    => 'deposit',
            'deposit_percent' => 40,
            'confirmed_at'    => now()->subDays(8),
        ]);
        // Deposit payment (40%)
        $dep = round($o3->total * 0.40, 2);
        EWOrderPayment::create(['order_id'=>$o3->id,'type'=>'deposit','method'=>'evc','amount'=>$dep,'status'=>'confirmed','paid_at'=>now()->subDays(7),'recorded_by'=>1]);
        // Balance payment (40% more — partial)
        $bal = round($o3->total * 0.40, 2);
        EWOrderPayment::create(['order_id'=>$o3->id,'type'=>'balance','method'=>'bank','amount'=>$bal,'status'=>'confirmed','paid_at'=>now()->subDays(3),'recorded_by'=>1]);
        $o3->update(['paid_total'=>$dep+$bal]);
        // Partial shipment (first product only)
        $firstItem = $o3->items->first();
        EWShipment::create([
            'order_id'     => $o3->id,
            'shipment_no'  => 'EWS-PARTIAL01',
            'items'        => [['item_id'=>$firstItem->id,'qty'=>$firstItem->qty]],
            'status'       => 'dispatched',
            'dispatched_at'=> now()->subDays(2),
            'note'         => 'First batch dispatched',
        ]);
        $firstItem->update(['shipped_qty' => $firstItem->qty]);

        // ── Order 4: processing ───────────────────────────────────────────
        $o4 = $makeOrder($buyer1, $sup2, [['product'=>$prod2,'qty'=>60]], ['status'=>'processing','confirmed_at'=>now()->subDays(3)]);
        EWOrderPayment::create(['order_id'=>$o4->id,'type'=>'full','method'=>'wallet','amount'=>$o4->total,'status'=>'confirmed','paid_at'=>now()->subDays(2),'recorded_by'=>1]);
        $o4->update(['paid_total'=>$o4->total]);

        // ── Order 5: awaiting_payment ─────────────────────────────────────
        $o5 = $makeOrder($buyer2, $sup1, [['product'=>$prod4,'qty'=>25]], ['status'=>'awaiting_payment','confirmed_at'=>now()->subDays(1)]);

        // ── Order 6: confirmed ────────────────────────────────────────────
        $o6 = $makeOrder($buyer3, $sup1, [['product'=>$prod3,'qty'=>40]], ['status'=>'confirmed','confirmed_at'=>now()]);

        // ── Orders 7-10: pending_confirmation ────────────────────────────
        for ($i = 0; $i < 4; $i++) {
            $makeOrder($buyers->random(), $suppliers->random(), [
                ['product'=>$products->random(),'qty'=>rand(10,80)],
            ]);
        }

        // ── 2 Disputes ────────────────────────────────────────────────────
        EWDispute::create([
            'order_id'    => $o2->id,
            'opened_by'   => $buyer2->user_id,
            'reason'      => 'Quality issue',
            'description' => 'Received products do not match sample quality. Several bags were damaged.',
            'status'      => 'open',
        ]);
        $o2->update(['status' => 'disputed']);

        EWDispute::create([
            'order_id'    => $o4->id,
            'opened_by'   => $buyer1->user_id,
            'reason'      => 'Short shipment',
            'description' => 'Only 50 out of 60 cartons were delivered. 10 cartons are missing.',
            'status'      => 'supplier_responded',
        ]);

        // ── 3 RFQs with quotes ────────────────────────────────────────────
        $rfq1 = EWRfq::create([
            'buyer_id'    => $buyer1->id,
            'title'       => 'Bulk Sugar — 500 bags',
            'description' => 'Looking for best price on white refined sugar, 50kg bags. Need FOB Mogadishu.',
            'qty'         => 500,
            'unit'        => 'bag',
            'target_price'=> 18.00,
            'needed_by'   => now()->addDays(14)->toDateString(),
            'status'      => 'open',
            'expires_at'  => now()->addDays(7),
        ]);
        EWRfqQuote::create(['rfq_id'=>$rfq1->id,'supplier_id'=>$sup1->id,'unit_price'=>17.50,'qty_offered'=>500,'lead_time_days'=>5,'valid_until'=>now()->addDays(10)->toDateString(),'status'=>'sent','note'=>'Price includes delivery to port']);
        EWRfqQuote::create(['rfq_id'=>$rfq1->id,'supplier_id'=>$sup2->id,'unit_price'=>16.80,'qty_offered'=>500,'lead_time_days'=>7,'valid_until'=>now()->addDays(12)->toDateString(),'status'=>'shortlisted','note'=>'Available from next week']);

        $rfq2 = EWRfq::create([
            'buyer_id'    => $buyer2->id,
            'title'       => 'Rice — 200 sacks Basmati',
            'description' => 'Sourcing Basmati rice 25kg sacks for retail distribution.',
            'qty'         => 200,
            'unit'        => 'sack',
            'target_price'=> 22.00,
            'needed_by'   => now()->addDays(21)->toDateString(),
            'status'      => 'open',
            'expires_at'  => now()->addDays(10),
        ]);
        EWRfqQuote::create(['rfq_id'=>$rfq2->id,'supplier_id'=>$sup1->id,'unit_price'=>21.50,'qty_offered'=>200,'lead_time_days'=>3,'status'=>'sent']);

        $rfq3 = EWRfq::create([
            'buyer_id'    => $buyer3->id,
            'title'       => 'Cooking Oil — 1000 cartons',
            'description' => 'Sunflower cooking oil 1L bottles, 12 per carton.',
            'qty'         => 1000,
            'unit'        => 'carton',
            'target_price'=> 15.00,
            'needed_by'   => now()->addDays(30)->toDateString(),
            'status'      => 'awarded',
            'expires_at'  => now()->subDays(2), // already expired (awarded)
        ]);
        EWRfqQuote::create(['rfq_id'=>$rfq3->id,'supplier_id'=>$sup2->id,'unit_price'=>14.50,'qty_offered'=>1000,'lead_time_days'=>10,'status'=>'accepted','note'=>'Order placed']);

        $this->command->info('✅ Demo data seeded:');
        $this->command->line('  10 orders (1 completed, 1 delivered, 1 deposit/partial, 1 processing, 1 awaiting_payment, 1 confirmed, 4 pending)');
        $this->command->line('  2 open disputes');
        $this->command->line('  3 RFQs with quotes (2 open, 1 awarded)');
        $this->command->line('  Default settings seeded');
    }
}
