<?php

namespace App\Console\Commands;

use App\Exceptions\Upf\FeeBillingException;
use App\Models\FeeType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Upf\UpfService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * PRD §10 / DEPLOYMENT.md §2 — dijalankan via Laravel Scheduler bulanan
 * (routes/console.php). Hanya jenis iuran `unit_type = flat` yang aman
 * dibuatkan tagihan otomatis; iuran berbasis meteran (per_meter/per_m2)
 * butuh pembacaan meter manual dan tetap dibuat lewat form staf.
 */
#[Signature('upf:generate-tagihan-periodik {period? : Periode YYYY-MM, default bulan berjalan}')]
#[Description('Menjalankan batch pembuatan tagihan iuran periodik (flat) untuk seluruh kios aktif')]
class GenerateUpfPeriodicBilling extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(UpfService $upfService): int
    {
        $period = $this->argument('period') ?? now()->format('Y-m');

        $systemUser = User::whereHas('roles', fn ($query) => $query->where('name', 'admin_sistem'))->first();

        if ($systemUser === null) {
            $this->error('Tidak ada user dengan role admin_sistem untuk menjalankan batch tagihan iuran.');

            return self::FAILURE;
        }

        $tenants = Tenant::query()->where('status', 'aktif')->get();
        $feeTypes = FeeType::query()->where('is_active', true)->where('unit_type', 'flat')->get();

        $created = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            foreach ($feeTypes as $feeType) {
                try {
                    $upfService->generateBilling($tenant, $feeType, $period, $systemUser->id);
                    $created++;
                } catch (FeeBillingException) {
                    $skipped++;
                }
            }
        }

        $this->info("Tagihan iuran periode {$period}: {$created} tagihan dibuat, {$skipped} dilewati (sudah ada).");

        return self::SUCCESS;
    }
}
