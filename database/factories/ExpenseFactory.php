<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'payroll_id' => null,
            'expense_date' => today(),
            'category' => 'General',
            'payee' => fake()->company(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'payment_mode' => 'cash',
            'transaction_ref' => null,
            'description' => fake()->sentence(),
            'created_by' => null,
        ];
    }
}
