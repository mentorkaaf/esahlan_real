<?php

namespace Database\Factories\EWholesale;

use App\Models\EWholesale\EWBuyer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EWBuyerFactory extends Factory
{
    protected $model = EWBuyer::class;

    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'business_name' => $this->faker->company(),
            'business_type' => 'shop',
            'kyb_status'    => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(['kyb_status' => 'approved']);
    }
}
