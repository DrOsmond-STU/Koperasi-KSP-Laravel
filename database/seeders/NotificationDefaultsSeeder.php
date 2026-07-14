<?php

namespace Database\Seeders;

use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * PRD §16 default templates & jadwal — Admin dapat mengubah keduanya
 * tanpa perubahan kode.
 */
class NotificationDefaultsSeeder extends Seeder
{
    private const SCHEDULES = [
        ['trigger_type' => 'jatuh_tempo_angsuran', 'day_offset' => -3, 'send_time' => '08:00:00'],
        ['trigger_type' => 'jatuh_tempo_angsuran', 'day_offset' => -1, 'send_time' => '08:00:00'],
        ['trigger_type' => 'jatuh_tempo_angsuran', 'day_offset' => 0, 'send_time' => '08:00:00'],
    ];

    private const TEMPLATES = [
        [
            'trigger_type' => 'jatuh_tempo_angsuran',
            'channel' => 'whatsapp',
            'subject' => null,
            'body' => 'Yth. {nama_anggota}, angsuran pinjaman {no_pinjaman} sebesar {nominal_angsuran} jatuh tempo pada {tanggal_jatuh_tempo}. Mohon segera melakukan pembayaran di {nama_cabang}. Terima kasih.',
        ],
        [
            'trigger_type' => 'jatuh_tempo_angsuran',
            'channel' => 'email',
            'subject' => 'Pengingat Jatuh Tempo Angsuran — {no_pinjaman}',
            'body' => 'Yth. {nama_anggota},\n\nAngsuran pinjaman {no_pinjaman} sebesar {nominal_angsuran} jatuh tempo pada {tanggal_jatuh_tempo}. Mohon segera melakukan pembayaran di {nama_cabang}.\n\nTerima kasih.',
        ],
        [
            'trigger_type' => 'kegiatan_dibatalkan',
            'channel' => 'whatsapp',
            'subject' => null,
            'body' => 'Yth. {nama_anggota}, kegiatan "{judul_kegiatan}" yang dijadwalkan pada {tanggal_kegiatan} telah DIBATALKAN. Mohon maaf atas ketidaknyamanannya.',
        ],
        [
            'trigger_type' => 'kegiatan_dibatalkan',
            'channel' => 'email',
            'subject' => 'Pembatalan Kegiatan — {judul_kegiatan}',
            'body' => 'Yth. {nama_anggota},\n\nKegiatan "{judul_kegiatan}" yang dijadwalkan pada {tanggal_kegiatan} telah DIBATALKAN.\n\nMohon maaf atas ketidaknyamanannya.',
        ],
    ];

    public function run(): void
    {
        foreach (self::SCHEDULES as $schedule) {
            NotificationSchedule::query()->firstOrCreate(
                ['trigger_type' => $schedule['trigger_type'], 'day_offset' => $schedule['day_offset']],
                ['send_time' => $schedule['send_time'], 'is_active' => true],
            );
        }

        foreach (self::TEMPLATES as $template) {
            NotificationTemplate::query()->firstOrCreate(
                ['trigger_type' => $template['trigger_type'], 'channel' => $template['channel']],
                ['subject' => $template['subject'], 'body' => $template['body'], 'is_active' => true],
            );
        }

        $this->command?->info('Seeded default jadwal & template notifikasi.');
    }
}
