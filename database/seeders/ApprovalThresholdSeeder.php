<?php

namespace Database\Seeders;

use App\Models\ApprovalThreshold;
use Illuminate\Database\Seeder;

/**
 * Ambang nilai default untuk approval berjenjang (PRD §13.4/§14.2). Nilai
 * ini dapat diubah oleh Admin Sistem/Manajer nanti tanpa perubahan kode.
 */
class ApprovalThresholdSeeder extends Seeder
{
    private const DEFAULTS = [
        'pembelian' => 5000000,
        'aktiva_tetap' => 10000000,
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $module => $threshold) {
            ApprovalThreshold::query()->firstOrCreate(
                ['module' => $module],
                ['threshold_amount' => $threshold],
            );
        }

        $this->command?->info('Seeded default ambang approval untuk pembelian & aktiva tetap.');
    }
}
