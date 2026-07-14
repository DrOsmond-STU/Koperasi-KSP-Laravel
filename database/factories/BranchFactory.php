<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'CBG-'.$this->faker->unique()->numerify('##'),
            'name' => 'Cabang '.$this->faker->city(),
            'address' => $this->faker->address(),
            'is_active' => true,
        ];
    }
}
