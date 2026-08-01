<?php

namespace Database\Factories;

use App\Models\MemberCardTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberCardTemplate>
 */
class MemberCardTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Template '.$this->faker->unique()->word(),
            'width_mm' => 85.60,
            'height_mm' => 53.98,
            'background_color' => '#FFFFFF',
            'is_active' => false,
        ];
    }
}
