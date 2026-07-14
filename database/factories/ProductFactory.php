<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'BRG-'.$this->faker->unique()->numerify('###'),
            'name' => 'Barang Uji',
            'category' => 'Sembako',
            'unit' => 'pcs',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'coa_inventory_account_id' => ChartOfAccount::factory(),
            'coa_cogs_account_id' => ChartOfAccount::factory(),
            'coa_sales_revenue_account_id' => ChartOfAccount::factory(),
            'is_active' => true,
        ];
    }
}
