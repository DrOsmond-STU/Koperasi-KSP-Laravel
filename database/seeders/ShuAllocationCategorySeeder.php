<?php

namespace Database\Seeders;

use App\Models\ShuAllocationCategory;
use Illuminate\Database\Seeder;

/**
 * Contoh alokasi SHU default (PRD §5.4, UU 25/1992) — total 100%. Admin
 * dapat mengubah persentase/kategori sesuai AD/ART koperasi masing-masing.
 */
class ShuAllocationCategorySeeder extends Seeder
{
    private const DEFAULTS = [
        ['name' => 'Cadangan Koperasi', 'percentage' => 40, 'is_jasa_anggota' => false],
        ['name' => 'Jasa Anggota', 'percentage' => 25, 'is_jasa_anggota' => true],
        ['name' => 'Dana Pengurus', 'percentage' => 5, 'is_jasa_anggota' => false],
        ['name' => 'Dana Karyawan', 'percentage' => 5, 'is_jasa_anggota' => false],
        ['name' => 'Dana Pendidikan', 'percentage' => 5, 'is_jasa_anggota' => false],
        ['name' => 'Dana Sosial', 'percentage' => 10, 'is_jasa_anggota' => false],
        ['name' => 'Dana Pembangunan Daerah Kerja', 'percentage' => 10, 'is_jasa_anggota' => false],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $category) {
            ShuAllocationCategory::query()->firstOrCreate(
                ['name' => $category['name']],
                ['percentage' => $category['percentage'], 'is_jasa_anggota' => $category['is_jasa_anggota'], 'is_active' => true],
            );
        }

        $this->command?->info('Seeded default alokasi SHU (total 100%).');
    }
}
