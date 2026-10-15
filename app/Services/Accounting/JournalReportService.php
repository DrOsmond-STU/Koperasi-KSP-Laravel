<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Laporan Jurnal Transaksi — daftar entri jurnal beserta baris debet/kredit
 * lengkapnya, lintas semua sumber transaksi.
 *
 * Bedanya dengan dua layar akuntansi yang sudah ada, dan kenapa keduanya
 * tidak cukup:
 *
 * - Buku Besar (GeneralLedgerService) berpusat pada AKUN: pilih satu akun,
 *   lihat mutasinya. Berguna untuk menelusuri saldo satu akun, tapi tidak
 *   pernah memperlihatkan satu transaksi secara utuh — lawan debet/kreditnya
 *   ada di kartu akun yang lain.
 * - Jurnal Umum (GeneralJournalController) memang menampilkan entri beserta
 *   barisnya, tapi daftarnya sengaja `whereNull('source_type')`: hanya
 *   posting manual. Jurnal yang lahir dari transaksi (pencairan pinjaman,
 *   angsuran, simpanan, retribusi, kas toko) tidak pernah muncul di sana.
 *
 * Akibatnya, sampai laporan ini ada, pertanyaan sesederhana "pencairan
 * pinjaman ini dijurnal ke mana saja?" tidak bisa dijawab dari dalam
 * aplikasi — laporan staf 15 Sep 2026: "tidak bisa cek jurnal karena tidak
 * ada fitur cek jurnal". Di produksi saat itu 1.096 dari 5.874 entri
 * berasal dari transaksi dan seluruhnya tidak terlihat sebagai jurnal.
 *
 * Kelas ini murni baca. Penulis journal_entries/journal_lines tetap hanya
 * JournalEngine (append-only, LED-01..08).
 */
class JournalReportService
{
    /**
     * Nama transaksi yang memunculkan jurnal, dalam bahasa yang dipakai
     * pengurus — bukan nama kelas PHP.
     *
     * Dikunci dengan class_basename, bukan nama kelas lengkap, supaya
     * penggantian namespace atau model yang belum ada tidak membuat
     * laporan gagal — sumber yang tidak dikenal tetap tampil dengan nama
     * kelasnya, bukan kolom kosong.
     */
    private const LABEL_SUMBER = [
        'Loan' => 'Pencairan Pinjaman',
        'LoanRepayment' => 'Angsuran Pinjaman',
        'SavingsAccount' => 'Transaksi Simpanan',
        'SavingsTransaction' => 'Transaksi Simpanan',
        'RetributionTransaction' => 'Retribusi UPF',
        'OpeningBalanceBatch' => 'Saldo Awal Migrasi',
        'PosSale' => 'Penjualan Toko',
        'Purchase' => 'Pembelian Persediaan',
        'InventoryReturn' => 'Retur Persediaan',
        'StockAdjustment' => 'Penyesuaian Stok',
        'FixedAsset' => 'Aset Tetap',
        'BusinessUnit' => 'Unit Usaha',
        'TellerCashTransaction' => 'Kas Teller',
    ];

    public const SUMBER_MANUAL = 'manual';

    /**
     * @param  array{
     *     date_from?: ?string, date_to?: ?string, branch_id?: ?int,
     *     chart_of_account_id?: ?int, source_type?: ?string, q?: ?string
     * }  $filter
     */
    public function paginate(array $filter, int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filter)
            ->with(['lines.account', 'branch', 'createdBy', 'reversals:id,reversal_of_entry_id'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Versi tanpa halaman untuk cetakan. Dibatasi keras supaya permintaan
     * cetak atas rentang tanggal yang kelewat lebar tidak menghabiskan
     * memori — view cetakannya menyebutkan batas ini kalau kena.
     *
     * @param  array<string, mixed>  $filter
     * @return Collection<int, JournalEntry>
     */
    public function all(array $filter, int $limit = 1000): Collection
    {
        return $this->query($filter)
            ->with(['lines.account', 'branch', 'createdBy', 'reversals:id,reversal_of_entry_id'])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Total debet/kredit seluruh hasil filter — bukan cuma halaman yang
     * sedang dilihat. Keduanya harus sama besar; kalau tidak, ada entri
     * tidak seimbang yang lolos dan itu perlu kelihatan.
     *
     * @param  array<string, mixed>  $filter
     * @return array{entries: int, lines: int, debit: string, credit: string, balanced: bool}
     */
    public function totals(array $filter): array
    {
        $sums = JournalLine::query()
            ->whereIn('journal_entry_id', $this->query($filter)->select('journal_entries.id'))
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit, COUNT(*) as total_lines')
            ->first();

        $debit = (string) $sums->total_debit;
        $credit = (string) $sums->total_credit;

        return [
            'entries' => $this->query($filter)->count(),
            'lines' => (int) $sums->total_lines,
            'debit' => $debit,
            'credit' => $credit,
            'balanced' => abs((float) $debit - (float) $credit) < 0.005,
        ];
    }

    /**
     * Sumber transaksi yang benar-benar ada di buku jurnal, untuk mengisi
     * dropdown filter — sengaja tidak memakai daftar LABEL_SUMBER di atas
     * supaya pengguna tidak ditawari pilihan yang pasti nihil hasilnya.
     *
     * @return array<string, string> nilai filter => label
     */
    public function sumberTersedia(): array
    {
        $opsi = [self::SUMBER_MANUAL => $this->labelSumber(null)];

        foreach (JournalEntry::query()->distinct()->pluck('source_type') as $sourceType) {
            if ($sourceType !== null && $sourceType !== '') {
                $opsi[$sourceType] = $this->labelSumber($sourceType);
            }
        }

        asort($opsi);

        return $opsi;
    }

    public function labelSumber(?string $sourceType): string
    {
        if ($sourceType === null || $sourceType === '') {
            return 'Jurnal Umum (manual)';
        }

        return self::LABEL_SUMBER[class_basename($sourceType)] ?? class_basename($sourceType);
    }

    /**
     * BranchScope pada JournalEntry sudah membatasi hasil ke cabang yang
     * boleh dilihat pengguna, jadi `branch_id` di sini adalah penyempitan
     * pilihan pengguna di atas batas itu — bukan satu-satunya pengaman.
     *
     * @param  array<string, mixed>  $filter
     * @return Builder<JournalEntry>
     */
    private function query(array $filter): Builder
    {
        $akun = $filter['chart_of_account_id'] ?? null;
        $sumber = $filter['source_type'] ?? null;
        $cari = trim((string) ($filter['q'] ?? ''));

        return JournalEntry::query()
            ->when($filter['date_from'] ?? null, fn ($q, $v) => $q->whereDate('entry_date', '>=', $v))
            ->when($filter['date_to'] ?? null, fn ($q, $v) => $q->whereDate('entry_date', '<=', $v))
            ->when($filter['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            // Entri yang MENYENTUH akun ini — barisnya tetap ditampilkan
            // utuh, karena yang dicari pengguna justru lawan debet/kreditnya.
            ->when($akun, fn ($q, $v) => $q->whereHas('lines', fn ($l) => $l->where('chart_of_account_id', $v)))
            ->when($sumber === self::SUMBER_MANUAL, fn ($q) => $q->whereNull('source_type'))
            ->when($sumber !== null && $sumber !== self::SUMBER_MANUAL, fn ($q) => $q->where('source_type', $sumber))
            ->when($cari !== '', fn ($q) => $q->where('description', 'LIKE', '%'.$cari.'%'));
    }
}
