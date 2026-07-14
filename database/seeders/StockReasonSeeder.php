<?php

namespace Database\Seeders;

use App\Models\StockReason;
use Illuminate\Database\Seeder;

/**
 * PRD §13.5/§13.6 — dipakai bersama oleh Retur Pembelian & Koreksi Persediaan.
 */
class StockReasonSeeder extends Seeder
{
    private const DEFAULTS = [
        'Rusak',
        'Hilang',
        'Kadaluarsa',
        'Kesalahan Input',
        'Retur ke Supplier',
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $name) {
            StockReason::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        $this->command?->info('Seeded default alasan mutasi persediaan.');
    }
}
