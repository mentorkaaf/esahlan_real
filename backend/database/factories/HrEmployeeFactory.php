<?php

namespace Database\Factories;

use App\Models\HR\HrEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrEmployeeFactory extends Factory
{
    protected $model = HrEmployee::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'employee_no'     => 'ESH-EMP-' . str_pad($seq++, 4, '0', STR_PAD_LEFT),
            'first_name'      => $this->faker->firstName,
            'last_name'       => $this->faker->lastName,
            'email'           => $this->faker->unique()->safeEmail,
            'phone'           => $this->faker->phoneNumber,
            'status'          => 'active',
            'employment_type' => 'full_time',
            'hire_date'       => now()->subYear()->format('Y-m-d'),
            'base_salary'     => 1500.00,
            'user_id'         => null,
            'department_id'   => null,
            'position_id'     => null,
        ];
    }
}
