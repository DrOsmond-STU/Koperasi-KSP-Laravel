<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
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
            'member_type_id' => MemberType::factory(),
            'member_number' => 'AGT-'.$this->faker->unique()->numerify('####'),
            'name' => $this->faker->name(),
            'nik' => $this->faker->numerify('################'),
            'address' => $this->faker->address(),
            'phone' => '628'.$this->faker->numerify('##########'),
            'email' => $this->faker->safeEmail(),
            'date_of_birth' => $this->faker->date(),
            'status' => 'aktif',
            'joined_at' => now()->toDateString(),
        ];
    }
}
