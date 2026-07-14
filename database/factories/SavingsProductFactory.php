<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\SavingsProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsProduct>
 */
class SavingsProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SIM-'.$this->faker->unique()->numerify('###'),
            'name' => 'Simpanan Sukarela Uji',
            'category' => 'sukarela',
            'interest_method' => 'flat',
            'minimum_initial_deposit' => 10000,
            'minimum_subsequent_deposit' => 5000,
            'coa_liability_account_id' => ChartOfAccount::factory(),
            'coa_interest_expense_account_id' => ChartOfAccount::factory(),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SavingsProduct $product) {
            $product->rateHistory()->create([
                'rate_percentage' => 2.5,
                'effective_from' => now()->subYear()->toDateString(),
            ]);
        });
    }
}
