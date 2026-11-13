<?php

namespace App\Services\Accounting;

use App\Exceptions\Accounting\CashAccountException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\Settings\CashSettingsService;

/**
 * Satu-satunya tempat yang menentukan akun kas lawan sebuah transaksi.
 *
 * Sebelumnya sepuluh service memutuskannya sendiri-sendiri lewat konstanta
 * `DEFAULT_CASH_ACCOUNT_CODE = '1101'` masing-masing. Pola itu punya tiga
 * masalah sekaligus, dan ketiganya sudah terbukti di produksi:
 *
 * 1. Koperasi yang membawa bagan akunnya sendiri tidak memakai 1101 dan
 *    menjadikannya akun header. Akun header ditolak JournalEngine, jadi
 *    setiap alur yang jatuh ke konstanta itu gagal total — persetujuan
 *    pinjaman balas HTTP 500 pada 15 Sep 2026 karena ini.
 * 2. Perbaikannya berjalan sepotong-sepotong. Angsuran dan simpanan sempat
 *    dialihkan ke akun kas cabang, alur lain tidak — dan tiap tambalan
 *    membawa aturannya sendiri yang tidak terlihat dari luar.
 * 3. Tambalannya sendiri ikut jadi hardcode: retribusi berpindah dari '1101'
 *    ke '1101600', yaitu nomor akun milik satu koperasi tertentu, di dalam
 *    kode yang dipakai semua koperasi.
 *
 * Urutan penentuannya: akun yang dipilih staf secara eksplisit, lalu akun
 * kas cabang (branches.cash_account_id, diatur lewat Pengaturan → Kas
 * Cabang), baru akun kas bawaan dari config `koperasi.akun_kas_bawaan`.
 * Apa pun hasilnya, akun itu diperiksa dulu keterpostingannya di sini
 * supaya kegagalannya menyebut cabang dan layar yang harus dibuka — bukan
 * baru ketahuan di JournalEngine sebagai "akun sekian adalah akun header".
 *
 * Satu alur sengaja keluar dari urutan itu: pencairan pinjaman, yang uangnya
 * keluar dari kas kecil unit dan bukan dari kas cabang tempat angsurannya
 * masuk — lihat forLoanDisbursement().
 */
class CashAccountResolver
{
    public function __construct(private readonly CashSettingsService $cashSettings) {}

    /**
     * Akun kas untuk transaksi milik sebuah cabang.
     *
     * $override dipakai alur yang membiarkan staf memilih rekening kas
     * sendiri di formulir (Teller, Kas Teller) — pilihan itu selalu menang.
     */
    public function forBranch(?int $branchId, ?ChartOfAccount $override = null): ChartOfAccount
    {
        if ($override !== null) {
            return $this->assertPostable($override, 'dipilih di formulir');
        }

        $branch = $branchId === null ? null : Branch::query()->find($branchId);

        if ($branch?->cashAccount !== null) {
            return $this->assertPostable($branch->cashAccount, "akun kas cabang {$branch->name}");
        }

        return $this->fallback(
            $branch === null
                ? CashAccountException::noBranchAndNoDefault()
                : CashAccountException::branchHasNoCashAccount($branch->name)
        );
    }

    /**
     * Akun kas sumber PENCAIRAN pinjaman.
     *
     * Sengaja tidak memakai akun kas cabang seperti angsuran. Uang pencairan
     * keluar dari kas kecil unit, sementara angsurannya masuk lewat kas AO —
     * keduanya memang akun berbeda, dan memaksa keduanya sama justru membuat
     * salah satunya salah. Ditambah `loans.branch_id` sendiri tidak bisa
     * dipercaya sebagai penunjuk unit (temuan 25 Agu 2026: 142 pinjaman aktif
     * seluruhnya tersimpan di cabang root "KPPD Pusat").
     *
     * Karena itu akunnya ditetapkan sekali di Pengaturan → Kas Cabang. Selama
     * belum diisi, perilakunya sama persis seperti dulu: akun kas cabang,
     * lalu akun kas bawaan.
     */
    public function forLoanDisbursement(?int $branchId): ChartOfAccount
    {
        $account = $this->cashSettings->loanDisbursementAccount();

        if ($account !== null) {
            return $this->assertPostable($account, 'kas pencairan pinjaman di Pengaturan → Kas Cabang');
        }

        return $this->forBranch($branchId);
    }

    /**
     * Akun kas milik satu cabang tertentu yang disebut lewat kodenya —
     * untuk alur yang memang selalu memakai kas cabang itu, terlepas dari
     * cabang yang tercatat di transaksinya (lihat `cabang_kas_simpanan`
     * dan `cabang_kas_retribusi` di config/koperasi.php).
     */
    public function forBranchCode(string $branchCode, ?ChartOfAccount $override = null): ChartOfAccount
    {
        if ($override !== null) {
            return $this->assertPostable($override, 'dipilih di formulir');
        }

        $branch = Branch::query()->where('code', $branchCode)->first();

        if ($branch?->cashAccount !== null) {
            return $this->assertPostable($branch->cashAccount, "akun kas cabang {$branch->name}");
        }

        return $this->fallback(
            $branch === null
                ? CashAccountException::branchNotFound($branchCode)
                : CashAccountException::branchHasNoCashAccount($branch->name)
        );
    }

    /**
     * Versi yang tidak melempar — untuk tampilan yang hanya ingin
     * memperlihatkan akun kas yang akan dipakai (pratinjau jurnal, banner
     * "Rekening Kas", cetakan rekap). Halaman seperti itu tidak boleh ikut
     * gagal hanya karena akun kasnya belum diatur.
     */
    public function tryForBranchCode(string $branchCode): ?ChartOfAccount
    {
        try {
            return $this->forBranchCode($branchCode);
        } catch (CashAccountException) {
            return null;
        }
    }

    /** @see tryForBranchCode() */
    public function tryForBranch(?int $branchId): ?ChartOfAccount
    {
        try {
            return $this->forBranch($branchId);
        } catch (CashAccountException) {
            return null;
        }
    }

    /**
     * Jaring pengaman terakhir. Kalau config-nya sengaja dikosongkan,
     * kegagalan aslinya yang dilempar — itu yang menyebut cabang mana yang
     * belum diatur, jauh lebih berguna daripada "akun bawaan tidak ada".
     */
    private function fallback(CashAccountException $reasonIfUnset): ChartOfAccount
    {
        $code = trim((string) config('koperasi.akun_kas_bawaan', ''));

        if ($code === '') {
            throw $reasonIfUnset;
        }

        $account = ChartOfAccount::query()->where('code', $code)->first();

        if ($account === null) {
            throw CashAccountException::defaultAccountMissing($code);
        }

        return $this->assertPostable($account, 'akun kas bawaan');
    }

    private function assertPostable(ChartOfAccount $account, string $source): ChartOfAccount
    {
        if (! $account->is_postable) {
            throw CashAccountException::notPostable($account->code, $account->name, $source);
        }

        return $account;
    }
}
