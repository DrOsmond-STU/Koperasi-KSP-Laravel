<?php

namespace Database\Factories;

use App\Models\RetributionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetributionType>
 */
class RetributionTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'RET-'.$this->faker->unique()->numerify('###'),
            'name' => 'Retribusi '.$this->faker->word(),
            'percentage' => 20,
            'coa_revenue_account_id' => null,
            'sort_order' => 0,
            'is_active' => false,
        ];
    }
}
