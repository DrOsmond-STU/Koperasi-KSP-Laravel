<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'member_id' => Member::factory(),
            'loan_product_id' => LoanProduct::factory(),
            'loan_number' => 'PINJ-'.$this->faker->unique()->numerify('########'),
            'principal_amount' => 5000000,
            'tenor_days' => 100,
            'tenor_unit' => 'hari',
            'interest_rate_percentage' => 12.0,
            'required_approval_count' => 1,
            'status' => 'diajukan',
            'created_by' => User::factory(),
            'submitted_at' => now()->toDateString(),
        ];
    }
}
