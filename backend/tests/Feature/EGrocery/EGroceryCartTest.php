<?php

namespace Tests\Feature\EGrocery;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\EGrocery\{EGroceryCategory, EGroceryProduct, EGroceryProductVariant};

class EGroceryCartTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private EGroceryProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $cat = EGroceryCategory::create([
            'name' => 'Test Cat', 'name_so' => 'Test', 'slug' => 'test-cat', 'is_active' => true,
        ]);

        $product = EGroceryProduct::create([
            'category_id' => $cat->id,
            'name' => 'Test Rice', 'name_so' => 'Bariis', 'slug' => 'test-rice',
            'is_active' => true,
        ]);

        $this->variant = EGroceryProductVariant::create([
            'product_id' => $product->id,
            'label'      => '1 kg',
            'price'      => '2.50',
            'stock_qty'  => 10,
            'is_active'  => true,
            'is_default' => true,
        ]);
    }

    /** @test */
    public function cart_validate_returns_correct_totals(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/egrocery/cart/validate', [
                'lines' => [
                    ['variant_id' => $this->variant->id, 'qty' => 2],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subtotal', 5.00)
            ->assertJsonPath('data.changed', false)
            ->assertJsonStructure(['data' => ['lines', 'subtotal', 'delivery_fee', 'total', 'warnings']]);
    }

    /** @test */
    public function cart_validate_caps_qty_to_stock(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/egrocery/cart/validate', [
                'lines' => [
                    ['variant_id' => $this->variant->id, 'qty' => 100], // more than stock (10)
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.changed', true);

        $line = $response->json('data.lines.0');
        $this->assertLessThanOrEqual(10, $line['qty']);
    }

    /** @test */
    public function cart_validate_skips_out_of_stock_variant(): void
    {
        $this->variant->update(['stock_qty' => 0]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/egrocery/cart/validate', [
                'lines' => [
                    ['variant_id' => $this->variant->id, 'qty' => 1],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.changed', true);

        $warnings = $response->json('data.warnings');
        $this->assertNotEmpty($warnings);
        $this->assertEquals('OUT_OF_STOCK', $warnings[0]['code']);
    }

    /** @test */
    public function cart_validate_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/egrocery/cart/validate', [
            'lines' => [['variant_id' => $this->variant->id, 'qty' => 1]],
        ]);

        $response->assertStatus(401);
    }
}
