<?php

namespace Database\Factories;

use App\Models\{User, Vendor};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'uuid'       => Str::uuid(),
            'user_id'    => User::factory(),
            'name'       => $name,
            'slug'       => Str::slug($name) . '-' . $this->faker->unique()->numerify('###'),
            'module_id'  => 6,           // eWholesale module_id
            'module_slug'=> 'ewholesale',
            'status'     => 'approved',
            'is_active'  => true,
            'is_approved'=> true,
        ];
    }
}
