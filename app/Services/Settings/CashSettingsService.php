<?php

namespace App\Services\Settings;

use App\Models\Branch;
use App\Models\CashSetting;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Cache;

/**
 * Setelan kas tingkat koperasi — selalu satu baris, dibaca lewat cache
 * selamanya. Mirrors PrintSettingsService / BrandingService exactly.
 */
class CashSettingsService
{
    private const CACHE_KEY = 'cash_settings';

    public function current(): CashSetting
    {
        $attributes = Cache::rememberForever(self::CACHE_KEY, function () {
            return CashSetting::query()->firstOrCreate(['id' => 1])->getAttributes();
        });

        return (new CashSetting)->newInstance($attributes, exists: true);
    }

    /**
     * Akun kas sumber pencairan pinjaman, atau null kalau belum diatur —
     * dalam hal itu CashAccountResolver kembali ke resolusi per cabang.
     */
    public function loanDisbursementAccount(): ?ChartOfAccount
    {
        $id = $this->current()->loan_disbursement_account_id;

        return $id === null ? null : ChartOfAccount::query()->find($id);
    }

    /**
     * Cabang pemilik seluruh transaksi pinjaman (pengajuan, pencairan,
     * angsuran), atau null kalau belum diatur — dalam hal itu cabangnya
     * tetap diturunkan dari cabang anggota seperti perilaku lama.
     *
     * Yang dikembalikan id-nya saja: pemanggilnya hanya butuh mengisi
     * kolom branch_id, dan menghindari query Branch di jalur posting
     * jurnal yang dipanggil per transaksi.
     */
    public function loanBranchId(): ?int
    {
        $id = $this->current()->loan_branch_id;

        return $id === null ? null : (int) $id;
    }

    public function loanBranch(): ?Branch
    {
        $id = $this->loanBranchId();

        return $id === null ? null : Branch::query()->find($id);
    }

    public function update(?int $loanDisbursementAccountId, int $userId, ?int $loanBranchId = null): CashSetting
    {
        $setting = CashSetting::query()->firstOrCreate(['id' => 1]);

        $setting->update([
            'loan_disbursement_account_id' => $loanDisbursementAccountId,
            'loan_branch_id' => $loanBranchId,
            'updated_by' => $userId,
        ]);

        Cache::forget(self::CACHE_KEY);

        return $setting->fresh();
    }
}
