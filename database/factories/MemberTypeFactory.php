<?php

namespace Database\Factories;

use App\Models\MemberType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberType>
 */
class MemberTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ANG-'.$this->faker->unique()->numerify('###'),
            'name' => 'Anggota Biasa',
            'has_voting_rights' => true,
            'requires_mandatory_savings' => true,
            'mandatory_savings_default_amount' => 100000,
            'counts_toward_shu' => true,
            'is_active' => true,
        ];
    }
}
