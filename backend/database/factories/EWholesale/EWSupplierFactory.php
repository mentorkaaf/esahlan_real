<?php

namespace Database\Factories\EWholesale;

use App\Models\EWholesale\EWSupplier;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class EWSupplierFactory extends Factory
{
    protected $model = EWSupplier::class;

    public function definition(): array
    {
        return [
            'vendor_id'           => Vendor::factory(),
            'display_name'        => $this->faker->company(),
            'verification'        => 'verified',
            'is_active'           => true,
            'response_rate'       => 95.00,
            'response_time_avg'   => 60,
            'rating'              => 4.5,
            'total_orders'        => 0,
        ];
    }
}
