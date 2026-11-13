<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanProduct>
 */
class LoanProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PINJ-'.$this->faker->unique()->numerify('###'),
            'name' => 'Pinjaman Modal Usaha Uji',
            'min_plafon' => 500000,
            'max_plafon' => 50000000,
            'min_tenor_days' => 7,
            'max_tenor_days' => 180,
            'calculation_method' => 'flat',
            'provision_fee_percentage' => 1,
            'penalty_percentage_per_day' => 0.1,
            'approval_threshold' => 10000000,
            'coa_receivable_account_id' => ChartOfAccount::factory(),
            'coa_interest_income_account_id' => ChartOfAccount::factory(),
            'coa_provision_income_account_id' => ChartOfAccount::factory(),
            'coa_penalty_receivable_account_id' => ChartOfAccount::factory(),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (LoanProduct $product) {
            $product->rateHistory()->create([
                'rate_percentage' => 12.0,
                'effective_from' => now()->subYear()->toDateString(),
            ]);
        });
    }
}
