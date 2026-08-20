<?php

namespace Tests\Feature\EWholesale;

use App\Models\EWholesale\{EWBuyer, EWCreditAccount, EWOrder, EWProduct, EWProductVariant, EWSupplier};
use App\Models\User;
use Database\Seeders\EWholesale\EWholesaleCatalogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartValidateTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────
    // Helper: create a minimal supplier + product + variant + price tier
    // ─────────────────────────────────────────────────────────────────────
    private function makeSupplierWithProduct(array $tiers = []): array
    {
        $supplier = EWSupplier::factory()->create([
            'allow_partial_payment' => false,
            'verification_status'   => 'verified',
        ]);

        $product = EWProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'unit'        => 'bag',
            'moq'         => 100,
            'is_active'   => true,
        ]);

        $variant = EWProductVariant::factory()->create([
            'product_id'   => $product->id,
            'stock_qty'    => 500,
            'reserved_qty' => 0,
            'is_active'    => true,
        ]);

        // Default tiers if none supplied: 100–499 at $50, 500+ at $40
        foreach ($tiers ?: [
            ['min_qty' => 100, 'max_qty' => 499, 'unit_price' => 50.00],
            ['min_qty' => 500, 'max_qty' => null, 'unit_price' => 40.00],
        ] as $tier) {
            $product->priceTiers()->create($tier);
        }

        return compact('supplier', 'product', 'variant');
    }

    private function makeApprovedBuyer(): array
    {
        $user  = User::factory()->create();
        $buyer = EWBuyer::factory()->create([
            'user_id'         => $user->id,
            'kyb_status'      => 'approved',
        ]);
        return compact('user', 'buyer');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Test 1: Tier resolution in cart validate
    // ─────────────────────────────────────────────────────────────────────
    public function test_cart_validate_resolves_correct_tier_price(): void
    {
        ['supplier' => $supplier, 'product' => $product, 'variant' => $variant] =
            $this->makeSupplierWithProduct();
        ['user' => $user] = $this->makeApprovedBuyer();

        // 200 bags → tier 100-499 → $50/bag
        $response = $this->actingAs($user)
            ->postJson('/api/v1/ewholesale/cart/validate', [
                'lines' => [[
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'qty'        => 200,
                ]],
            ]);

        $response->assertOk();
        $groups = $response->json('data.groups');
        $this->assertCount(1, $groups);
        $line = $groups[0]['lines'][0];
        $this->assertEquals(50.00, (float) $line['unit_price'], 'Tier 100-499 should resolve to $50');
        $this->assertEquals(200 * 50.00, (float) $line['subtotal']);

        // 600 bags → tier 500+ → $40/bag
        $response2 = $this->actingAs($user)
            ->postJson('/api/v1/ewholesale/cart/validate', [
                'lines' => [[
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'qty'        => 600,
                ]],
            ]);

        $response2->assertOk();
        $line2 = $response2->json('data.groups.0.lines.0');
        $this->assertEquals(40.00, (float) $line2['unit_price'], 'Tier 500+ should resolve to $40');
        $this->assertEquals(600 * 40.00, (float) $line2['subtotal']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Test 2: Credit-limit checkout rejection (CREDIT_EXCEEDED)
    // ─────────────────────────────────────────────────────────────────────
    public function test_checkout_rejects_when_credit_limit_exceeded(): void
    {
        ['supplier' => $supplier, 'product' => $product, 'variant' => $variant] =
            $this->makeSupplierWithProduct();
        ['user' => $user, 'buyer' => $buyer] = $this->makeApprovedBuyer();

        // Give buyer a credit account with a $500 limit, $400 already used
        EWCreditAccount::factory()->create([
            'buyer_id'     => $buyer->id,
            'credit_limit' => 500.00,
            'balance_used' => 400.00,  // column name in migration
            'status'       => 'active',
        ]);

        // 200 bags × $50 = $10,000 total — way over the $100 available credit
        $response = $this->actingAs($user)
            ->postJson('/api/v1/ewholesale/orders', [
                'groups' => [[
                    'supplier_id'  => $supplier->id,
                    'payment_plan' => 'credit',
                    'lines'        => [[
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'qty'        => 200,
                    ]],
                ]],
            ]);

        $response->assertStatus(422);
        $this->assertEquals('CREDIT_EXCEEDED', $response->json('error_code'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Test 3: Deposit → pay-balance flow
    // ─────────────────────────────────────────────────────────────────────
    public function test_deposit_plan_then_pay_balance(): void
    {
        ['supplier' => $supplier, 'product' => $product, 'variant' => $variant] =
            $this->makeSupplierWithProduct();
        ['user' => $user, 'buyer' => $buyer] = $this->makeApprovedBuyer();

        // Enable deposit plan via EWSetting
        \App\Models\EWholesale\EWSetting::updateOrCreate(
            ['key' => 'min_deposit_percent'],
            ['value' => '30', 'type' => 'decimal', 'label' => 'Min Deposit %']
        );
        \Illuminate\Support\Facades\Cache::forget('ew:setting:min_deposit_percent');

        // Checkout with deposit plan (30%)
        $checkoutResp = $this->actingAs($user)
            ->postJson('/api/v1/ewholesale/orders', [
                'groups' => [[
                    'supplier_id'     => $supplier->id,
                    'payment_plan'    => 'deposit',
                    'deposit_percent' => 30,
                    'lines'           => [[
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'qty'        => 100,
                    ]],
                ]],
            ]);

        $checkoutResp->assertStatus(201);
        $orderId = $checkoutResp->json('data.orders.0.id');

        // Order should be in awaiting_payment state (deposit charged, balance pending)
        $order = EWOrder::findOrFail($orderId);
        $this->assertEquals('awaiting_payment', $order->status);
        $this->assertEquals(30, (int) $order->deposit_percent);

        // Confirm deposit payment recorded
        $this->assertDatabaseHas('ewholesale_order_payments', [
            'order_id' => $orderId,
            'type'     => 'deposit',
        ]);

        // Pay balance
        $payResp = $this->actingAs($user)
            ->postJson("/api/v1/ewholesale/orders/{$orderId}/pay-balance", [
                'method' => 'wallet',
            ]);

        $payResp->assertOk();
        $this->assertDatabaseHas('ewholesale_order_payments', [
            'order_id' => $orderId,
            'type'     => 'balance',
        ]);

        // After balance payment, order should advance (confirmed or processing)
        $order->refresh();
        $this->assertNotEquals('awaiting_payment', $order->status);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Test 4: Multi-supplier cart split → 2 separate orders
    // ─────────────────────────────────────────────────────────────────────
    public function test_multi_supplier_cart_split_creates_separate_orders(): void
    {
        ['supplier' => $s1, 'product' => $p1, 'variant' => $v1] = $this->makeSupplierWithProduct();
        ['supplier' => $s2, 'product' => $p2, 'variant' => $v2] = $this->makeSupplierWithProduct();
        ['user' => $user, 'buyer' => $buyer] = $this->makeApprovedBuyer();

        // Validate shows 2 groups
        $validateResp = $this->actingAs($user)
            ->postJson('/api/v1/ewholesale/cart/validate', [
                'lines' => [
                    ['product_id' => $p1->id, 'variant_id' => $v1->id, 'qty' => 100],
                    ['product_id' => $p2->id, 'variant_id' => $v2->id, 'qty' => 100],
                ],
            ]);

        $validateResp->assertOk();
        $this->assertCount(2, $validateResp->json('data.groups'));

        // Checkout both groups → 2 orders created
        $checkoutResp = $this->actingAs($user)
            ->postJson('/api/v1/ewholesale/orders', [
                'groups' => [
                    [
                        'supplier_id'  => $s1->id,
                        'payment_plan' => 'prepaid',
                        'lines'        => [['product_id' => $p1->id, 'variant_id' => $v1->id, 'qty' => 100]],
                    ],
                    [
                        'supplier_id'  => $s2->id,
                        'payment_plan' => 'prepaid',
                        'lines'        => [['product_id' => $p2->id, 'variant_id' => $v2->id, 'qty' => 100]],
                    ],
                ],
            ]);

        $checkoutResp->assertStatus(201);
        $orders = $checkoutResp->json('data.orders');
        $this->assertCount(2, $orders, 'Should create one order per supplier');

        $supplierIds = collect($orders)->pluck('supplier_id')->sort()->values()->toArray();
        $expected    = collect([$s1->id, $s2->id])->sort()->values()->toArray();
        $this->assertEquals($expected, $supplierIds, 'Each order should belong to a different supplier');

        // Confirm both orders in DB with correct buyers
        $this->assertDatabaseHas('ewholesale_orders', ['supplier_id' => $s1->id, 'buyer_id' => $buyer->id]);
        $this->assertDatabaseHas('ewholesale_orders', ['supplier_id' => $s2->id, 'buyer_id' => $buyer->id]);
    }
}
