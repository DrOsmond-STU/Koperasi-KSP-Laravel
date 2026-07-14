<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SUP-'.$this->faker->unique()->numerify('###'),
            'name' => 'Supplier Uji',
            'type' => 'Distributor',
            'contact_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'payment_term' => 'tunai',
            'payment_term_days' => null,
            'coa_payable_account_id' => ChartOfAccount::factory(),
            'is_active' => true,
        ];
    }

    public function kredit(int $days = 30): self
    {
        return $this->state([
            'payment_term' => 'kredit',
            'payment_term_days' => $days,
        ]);
    }
}
