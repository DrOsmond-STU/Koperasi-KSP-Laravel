<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\FeeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeType>
 */
class FeeTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'IUR-'.$this->faker->unique()->numerify('###'),
            'name' => 'Iuran Kebersihan',
            'unit_type' => 'flat',
            'tariff' => 50000,
            'billing_period' => 'bulanan',
            'coa_receivable_account_id' => ChartOfAccount::factory(),
            'coa_revenue_account_id' => ChartOfAccount::factory(),
            'is_active' => true,
        ];
    }

    public function perMeter(): self
    {
        return $this->state([
            'name' => 'Iuran Listrik',
            'unit_type' => 'per_meter',
            'tariff' => 1500,
        ]);
    }
}
