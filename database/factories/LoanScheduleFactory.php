<?php

namespace Database\Factories;

use App\Models\LoanSchedule;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanScheduleFactory extends Factory
{
    protected $model = LoanSchedule::class;

    public function definition()
    {
        return [
            'loan_id' => Loan::factory(),
            'scheduled_date' => now()->addDays(rand(10, 60)),
            'amount_due' => $this->faker->numberBetween(50000, 200000),
            'status' => 'pendiente',
        ];
    }
}
