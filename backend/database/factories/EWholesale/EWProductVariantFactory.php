<?php

namespace Database\Factories\EWholesale;

use App\Models\EWholesale\{EWProduct, EWProductVariant};
use Illuminate\Database\Eloquent\Factories\Factory;

class EWProductVariantFactory extends Factory
{
    protected $model = EWProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id'    => EWProduct::factory(),
            'sku'           => strtoupper($this->faker->unique()->bothify('SKU-##??##')),
            'stock_qty'     => 500,
            'reserved_qty'  => 0,      // added in migration 000005
            'weight_kg'     => 1.0,
            'is_default'    => true,
            'is_active'     => true,
        ];
    }
}
