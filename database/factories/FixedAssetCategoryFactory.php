<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\FixedAssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedAssetCategory>
 */
class FixedAssetCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'KAT-'.$this->faker->unique()->numerify('###'),
            'name' => 'Peralatan Kantor',
            'default_depreciation_method' => 'garis_lurus',
            'default_useful_life_months' => 48,
            'coa_asset_account_id' => ChartOfAccount::factory(),
            'coa_accumulated_depreciation_account_id' => ChartOfAccount::factory(),
            'coa_depreciation_expense_account_id' => ChartOfAccount::factory(),
            'is_active' => true,
        ];
    }
}
