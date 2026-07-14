<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedAsset>
 */
class FixedAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'AT-'.$this->faker->unique()->numerify('####'),
            'name' => 'Laptop Kantor',
            'fixed_asset_category_id' => FixedAssetCategory::factory(),
            'branch_id' => Branch::factory(),
            'ownership_document_number' => null,
            'acquisition_date' => now()->subYear()->toDateString(),
            'acquisition_cost' => 12000000,
            'residual_value' => 1000000,
            'useful_life_months' => 36,
            'depreciation_method' => 'garis_lurus',
            'payment_method' => 'tunai',
            'status' => 'aktif',
            'created_by' => User::factory(),
        ];
    }
}
