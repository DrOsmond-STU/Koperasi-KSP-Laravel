<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus massal Bagan Akun — pendamping ChartOfAccountImportService, untuk
 * membersihkan akun yang salah import atau akun bawaan yang tidak dipakai
 * koperasi ini.
 *
 * Tiga belas tabel menunjuk chart_of_accounts lewat foreign key
 * restrictOnDelete, jadi database sudah menolak penghapusan akun terpakai —
 * tapi pesannya berupa SQLSTATE mentah. Service ini memetakan setiap rujukan
 * ke nama layar yang dikenal pengguna supaya alasan "kenapa tidak bisa
 * dihapus" bisa dibaca sebelum tombol ditekan, bukan sesudah gagal.
 */
class ChartOfAccountPurgeService
{
    /**
     * Tabel & kolom yang merujuk chart_of_accounts (semua restrictOnDelete),
     * beserta label layarnya. Dijaga manual: menambah foreign key baru ke
     * chart_of_accounts tanpa menambahkannya di sini membuat akun tampak
     * "bisa dihapus" padahal nanti ditolak database — penghapusannya tetap
     * aman (ditangkap sebagai gagal), hanya pratinjaunya jadi kurang tepat.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const REFERENCES = [
        ['journal_lines', 'chart_of_account_id', 'baris jurnal'],
        ['opening_balance_coa', 'chart_of_account_id', 'Saldo Awal COA'],
        ['savings_products', 'coa_liability_account_id', 'Produk Simpanan (akun simpanan)'],
        ['savings_products', 'coa_interest_expense_account_id', 'Produk Simpanan (beban jasa)'],
        ['loan_products', 'coa_receivable_account_id', 'Produk Pinjaman (piutang)'],
        ['loan_products', 'coa_interest_income_account_id', 'Produk Pinjaman (pendapatan jasa)'],
        ['loan_products', 'coa_provision_income_account_id', 'Produk Pinjaman (provisi)'],
        ['loan_products', 'coa_penalty_receivable_account_id', 'Produk Pinjaman (piutang denda)'],
        ['cash_categories', 'coa_account_id', 'Kategori Kas'],
        ['teller_cash_transactions', 'cash_account_id', 'Transaksi Kas Teller'],
        ['business_units', 'coa_revenue_account_id', 'Unit Usaha (pendapatan)'],
        ['business_units', 'coa_expense_account_id', 'Unit Usaha (beban)'],
        ['products', 'coa_inventory_account_id', 'Master Barang (persediaan)'],
        ['products', 'coa_cogs_account_id', 'Master Barang (HPP)'],
        ['products', 'coa_sales_revenue_account_id', 'Master Barang (pendapatan penjualan)'],
        ['suppliers', 'coa_payable_account_id', 'Supplier (utang usaha)'],
        ['fixed_asset_categories', 'coa_asset_account_id', 'Kategori Aktiva Tetap (aset)'],
        ['fixed_asset_categories', 'coa_accumulated_depreciation_account_id', 'Kategori Aktiva Tetap (akumulasi penyusutan)'],
        ['fixed_asset_categories', 'coa_depreciation_expense_account_id', 'Kategori Aktiva Tetap (beban penyusutan)'],
        ['retribution_types', 'coa_revenue_account_id', 'Jenis Retribusi'],
        ['retribution_transaction_lines', 'chart_of_account_id', 'baris transaksi Retribusi'],
    ];

    /**
     * Alasan setiap akun tidak bisa dihapus, dikumpulkan sekali untuk seluruh
     * tabel agar layar daftar tidak menembak satu query per baris.
     *
     * @return array<int, array<int, string>> id akun => daftar alasan
     */
    public function blockers(): array
    {
        $blockers = [];

        foreach (ChartOfAccount::query()->whereIn('code', ChartOfAccount::PROTECTED_CODES)->pluck('id') as $id) {
            $blockers[$id][] = 'akun inti sistem';
        }

        foreach (self::REFERENCES as [$table, $column, $label]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $ids = DB::table($table)->whereNotNull($column)->distinct()->pluck($column);

            foreach ($ids as $id) {
                $blockers[(int) $id][] = $label;
            }
        }

        return array_map('array_unique', $blockers);
    }

    /**
     * Jumlah akun anak per kode induk — dipakai layar daftar untuk menandai
     * akun yang baru bisa dihapus bersama anak-anaknya.
     *
     * @return array<string, int> parent_code => jumlah anak
     */
    public function childCounts(): array
    {
        return ChartOfAccount::query()
            ->whereNotNull('parent_code')
            ->selectRaw('parent_code, COUNT(*) as jumlah')
            ->groupBy('parent_code')
            ->pluck('jumlah', 'parent_code')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Hapus akun terpilih.
     *
     * Sengaja TIDAK dibungkus satu transaksi: ini alat pembersihan yang
     * dipakai berulang, dan satu akun terkunci tidak boleh membatalkan
     * penghapusan puluhan akun lain yang sudah berhasil. Setiap kegagalan
     * dilaporkan per baris supaya pengguna tahu persis apa yang tersisa.
     *
     * Penghapusan berjalan berlapis: akun yang masih punya anak dilewati dulu,
     * lalu diulang setelah anak-anaknya terhapus. Tanpa itu, memilih induk dan
     * anak sekaligus akan gagal di induk hanya karena urutan baris di layar.
     *
     * @param  array<int, int>  $ids
     * @return array{dihapus: int, gagal: array<int, array{code: string, name: string, alasan: string}>}
     */
    public function purge(array $ids): array
    {
        /** @var array<int, ChartOfAccount> $pending */
        $pending = ChartOfAccount::query()->whereIn('id', $ids)->orderBy('code')->get()->all();

        $dihapus = 0;
        $gagal = [];

        do {
            $ada = false;

            foreach ($pending as $key => $account) {
                if ($account->isProtected()) {
                    $gagal[] = $this->kegagalan($account, 'akun inti sistem — tidak boleh dihapus');
                    unset($pending[$key]);
                    $ada = true;

                    continue;
                }

                // Anak yang juga ikut dipilih akan terhapus di putaran
                // berikutnya; yang tersisa berarti anak di luar pilihan.
                if ($account->children()->exists()) {
                    continue;
                }

                try {
                    $account->delete();
                    $dihapus++;
                } catch (QueryException) {
                    $gagal[] = $this->kegagalan($account, 'masih dipakai transaksi atau master data lain');
                }

                unset($pending[$key]);
                $ada = true;
            }
        } while ($ada && $pending !== []);

        foreach ($pending as $account) {
            $gagal[] = $this->kegagalan($account, 'masih punya akun anak yang tidak ikut dipilih');
        }

        return ['dihapus' => $dihapus, 'gagal' => $gagal];
    }

    /**
     * @return array{code: string, name: string, alasan: string}
     */
    private function kegagalan(ChartOfAccount $account, string $alasan): array
    {
        return ['code' => $account->code, 'name' => $account->name, 'alasan' => $alasan];
    }
}
