<?php

namespace Database\Factories;

use App\Models\HR\HrDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrDepartmentFactory extends Factory
{
    protected $model = HrDepartment::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->unique()->word . ' Department',
            'code'      => strtoupper($this->faker->unique()->lexify('???')),
            'is_active' => true,
        ];
    }
}
