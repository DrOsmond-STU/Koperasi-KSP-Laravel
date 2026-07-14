<?php

namespace Database\Factories;

use App\Models\StockReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockReason>
 */
class StockReasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Rusak '.$this->faker->unique()->numerify('###'),
            'is_active' => true,
        ];
    }
}
