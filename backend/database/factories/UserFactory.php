<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'uuid'      => Str::uuid()->toString(),
            'name'      => $this->faker->name,
            'email'     => $this->faker->unique()->safeEmail,
            'phone'     => '+2526' . $this->faker->numerify('#######'),
            'password'  => bcrypt('password'),
            'status'    => 'active',
            'fcm_token' => null,
        ];
    }
}
