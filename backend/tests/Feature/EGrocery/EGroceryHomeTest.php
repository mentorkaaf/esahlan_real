<?php

namespace Tests\Feature\EGrocery;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EGroceryHomeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function home_payload_has_required_shape(): void
    {
        $response = $this->getJson('/api/v1/egrocery/home');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'banners',
                    'categories',
                    'sections',
                    'delivery_info' => ['delivery_fee', 'min_order'],
                    'buy_again',
                ],
            ]);
    }

    /** @test */
    public function home_returns_buy_again_when_authenticated(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/egrocery/home');

        $response->assertStatus(200)
            ->assertJsonPath('data.buy_again', fn ($v) => is_array($v));
    }

    /** @test */
    public function suggest_returns_products_and_categories(): void
    {
        $response = $this->getJson('/api/v1/egrocery/search/suggest?q=ri');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    /** @test */
    public function suggest_requires_at_least_2_chars(): void
    {
        $response = $this->getJson('/api/v1/egrocery/search/suggest?q=r');
        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }
}
