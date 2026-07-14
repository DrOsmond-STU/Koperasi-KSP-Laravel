<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessUnit>
 */
class BusinessUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'UNIT-'.$this->faker->unique()->numerify('###'),
            'name' => 'Unit Persewaan Aula',
            'coa_revenue_account_id' => ChartOfAccount::factory(),
            'coa_expense_account_id' => ChartOfAccount::factory(),
            'is_active' => true,
        ];
    }
}
