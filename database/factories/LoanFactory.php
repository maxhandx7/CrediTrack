<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'client_id' => Client::factory(),
            'amount' => $this->faker->numberBetween(100000, 1000000),
            'interest_rate' => 10,
            'interest_type' => 'simple',
            'start_date' => now(),
            'due_date' => now()->addMonths(6),
            'payment_frequency' => 'mensual',
            'status' => 'activo',
            'notes' => $this->faker->sentence,
        ];
    }
}
