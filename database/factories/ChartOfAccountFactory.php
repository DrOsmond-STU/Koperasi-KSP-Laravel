<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartOfAccount>
 */
class ChartOfAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('9###'),
            'name' => 'Akun Uji '.$this->faker->word(),
            'type' => 'LIABILITAS',
            'group' => 'Uji',
            'normal_balance' => 'KREDIT',
            'is_postable' => true,
            'parent_code' => null,
            'statement' => 'NERACA',
        ];
    }

    public function nonPostable(): self
    {
        return $this->state(['is_postable' => false]);
    }
}
