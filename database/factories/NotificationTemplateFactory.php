<?php

namespace Database\Factories;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trigger_type' => 'jatuh_tempo_angsuran',
            'channel' => 'whatsapp',
            'subject' => null,
            'body' => 'Yth. {nama_anggota}, angsuran {no_pinjaman} sebesar {nominal_angsuran} jatuh tempo {tanggal_jatuh_tempo}.',
            'is_active' => true,
        ];
    }
}
