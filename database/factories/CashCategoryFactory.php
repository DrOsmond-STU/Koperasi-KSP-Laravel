<?php

namespace Database\Factories;

use App\Models\CashCategory;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashCategory>
 */
class CashCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'KAS-'.$this->faker->unique()->numerify('###'),
            'name' => 'Kategori Kas Uji',
            'type' => 'masuk',
            'coa_account_id' => ChartOfAccount::factory(),
            'is_active' => true,
        ];
    }

    public function keluar(): self
    {
        return $this->state(['type' => 'keluar']);
    }
}
