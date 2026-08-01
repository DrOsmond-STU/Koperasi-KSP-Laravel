<?php

namespace Database\Seeders;

use App\Models\MemberType;
use Illuminate\Database\Seeder;

/**
 * Master Jenis Anggota dinamis (PRD §7.1). Termasuk KIOS/BLOK — dipakai
 * sebagai sumber daftar pembayar pada modul Retribusi (UPF).
 */
class MemberTypeSeeder extends Seeder
{
    private const DEFAULTS = [
        ['code' => 'ANGGOTA', 'name' => 'Anggota Biasa'],
        ['code' => 'CALON', 'name' => 'Calon Anggota'],
        ['code' => 'KIOS', 'name' => 'Anggota Kios'],
        ['code' => 'BLOK', 'name' => 'Anggota Blok'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $type) {
            MemberType::query()->firstOrCreate(['code' => $type['code']], [
                'name' => $type['name'],
                'has_voting_rights' => true,
                'requires_mandatory_savings' => true,
                'counts_toward_shu' => true,
                'is_active' => true,
            ]);
        }

        $this->command?->info('Seeded default jenis anggota.');
    }
}
