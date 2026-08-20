<?php

namespace Database\Factories\EWholesale;

use App\Models\EWholesale\{EWProduct, EWSupplier};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EWProductFactory extends Factory
{
    protected $model = EWProduct::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        return [
            'supplier_id'    => EWSupplier::factory(),
            'category_id'    => \App\Models\EWholesale\EWCategory::factory(),
            'name'           => ucwords($name),
            'slug'           => Str::slug($name) . '-' . $this->faker->unique()->numerify('###'),
            'description'    => $this->faker->paragraph(),
            'unit'           => 'bag',
            'moq'            => 100,
            'lead_time_days' => 7,
            'min_price'      => 40.00,
            'status'         => 'active',
        ];
    }
}
