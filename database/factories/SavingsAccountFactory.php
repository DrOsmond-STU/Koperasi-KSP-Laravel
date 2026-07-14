<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsAccount>
 */
class SavingsAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'member_id' => Member::factory(),
            'savings_product_id' => SavingsProduct::factory(),
            'account_number' => 'SA-'.$this->faker->unique()->numerify('########'),
            'balance' => 0,
            'status' => 'aktif',
            'opened_at' => now()->toDateString(),
        ];
    }
}
