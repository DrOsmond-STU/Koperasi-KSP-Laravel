<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FixedAsset\DepreciationEngine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * PRD §14.2 — dijalankan via Laravel Scheduler bulanan (routes/console.php).
 * Journal entries need a `created_by` user; batch jobs run with no
 * authenticated user, so this resolves the first Admin Sistem account as
 * the system actor rather than making `created_by` nullable everywhere.
 */
#[Signature('depreciation:run {period? : Periode YYYY-MM, default bulan berjalan}')]
#[Description('Menjalankan batch penyusutan bulanan untuk seluruh aktiva tetap berstatus Aktif')]
class RunMonthlyDepreciation extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DepreciationEngine $engine): int
    {
        $period = $this->argument('period') ?? now()->format('Y-m');

        $systemUser = User::whereHas('roles', fn ($query) => $query->where('name', 'admin_sistem'))->first();

        if ($systemUser === null) {
            $this->error('Tidak ada user dengan role admin_sistem untuk menjalankan batch penyusutan.');

            return self::FAILURE;
        }

        $result = $engine->run($period, $systemUser->id);

        $this->info("Penyusutan periode {$period}: {$result['created']} aset diposting, {$result['skipped']} dilewati.");

        return self::SUCCESS;
    }
}
