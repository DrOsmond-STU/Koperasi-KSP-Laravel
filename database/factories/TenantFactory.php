<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
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
            'code' => 'KIOS-'.$this->faker->unique()->numerify('###'),
            'owner_name' => $this->faker->name(),
            'location' => 'Blok '.$this->faker->randomLetter(),
            'status' => 'aktif',
        ];
    }
}
