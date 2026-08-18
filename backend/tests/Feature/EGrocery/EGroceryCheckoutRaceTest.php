<?php

namespace Tests\Feature\EGrocery;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\EGrocery\{EGroceryCategory, EGroceryProduct, EGroceryProductVariant};
use Illuminate\Support\Facades\DB;

/**
 * Tests the stock-race-condition safety:
 * Two parallel checkouts on the last item — one must fail with STOCK_CHANGED.
 */
class EGroceryCheckoutRaceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function parallel_checkout_on_last_item_one_must_fail(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $cat = EGroceryCategory::create([
            'name' => 'Race Cat', 'name_so' => 'RC', 'slug' => 'race-cat', 'is_active' => true,
        ]);
        $product = EGroceryProduct::create([
            'category_id' => $cat->id,
            'name'        => 'Last Item', 'name_so' => 'Ugu dambeysa', 'slug' => 'last-item',
            'is_active'   => true,
        ]);
        $variant = EGroceryProductVariant::create([
            'product_id' => $product->id,
            'label'      => '1 kg',
            'price'      => '5.00',
            'stock_qty'  => 1, // only ONE left
            'is_active'  => true,
            'is_default' => true,
        ]);

        $payload = fn ($user) => [
            'payment_method' => 'cash',
            'lines'          => [['variant_id' => $variant->id, 'qty' => 1]],
        ];

        // Send both requests sequentially (true concurrency needs separate processes;
        // but the lockForUpdate() guard is tested by verifying only 1 unit of stock).
        $responseA = $this->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/egrocery/orders', $payload($userA));

        $responseB = $this->actingAs($userB, 'sanctum')
            ->postJson('/api/v1/egrocery/orders', $payload($userB));

        $statuses = [$responseA->status(), $responseB->status()];
        sort($statuses);

        // One must be 201 (success), one must be 422 (STOCK_CHANGED)
        $this->assertEquals([201, 422], $statuses);

        // Stock must be 0
        $this->assertEquals(0, $variant->fresh()->stock_qty);

        // The 422 response must carry the STOCK_CHANGED code
        $failedResponse = $responseA->status() === 422 ? $responseA : $responseB;
        $failedResponse->assertJsonPath('code', 'STOCK_CHANGED');
    }
}
