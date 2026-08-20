<?php

namespace Database\Factories\EWholesale;

use App\Models\EWholesale\EWCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EWCategoryFactory extends Factory
{
    protected $model = EWCategory::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true);
        return [
            'name'       => ucwords($name),
            'slug'       => Str::slug($name) . '-' . $this->faker->unique()->numerify('##'),
            'is_active'  => true,
            'sort_order' => 0,
        ];
    }
}
