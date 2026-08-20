<?php

namespace Database\Factories\EWholesale;

use App\Models\EWholesale\{EWBuyer, EWCreditAccount};
use Illuminate\Database\Eloquent\Factories\Factory;

class EWCreditAccountFactory extends Factory
{
    protected $model = EWCreditAccount::class;

    public function definition(): array
    {
        return [
            'buyer_id'     => EWBuyer::factory(),
            'credit_limit' => 1000.00,
            'balance_used'  => 0.00,
            'term'         => 'net30',
            'status'       => 'active',
        ];
    }
}
