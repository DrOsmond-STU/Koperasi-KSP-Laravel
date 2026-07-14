<?php

namespace Database\Factories;

use App\Models\CooperativeEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CooperativeEvent>
 */
class CooperativeEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Rapat Anggota Tahunan',
            'description' => null,
            'start_at' => now()->addWeek(),
            'end_at' => now()->addWeek()->addHours(2),
            'location' => 'Kantor Pusat',
            'branch_scope_type' => 'all',
            'participant_type' => 'semua_anggota_cabang',
            'reminder_days' => [3, 1, 0],
            'status' => 'terjadwal',
            'created_by' => User::factory(),
        ];
    }
}
