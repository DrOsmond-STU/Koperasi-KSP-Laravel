<?php

namespace App\Services\Retribution;

use App\Models\RetributionTransaction;
use App\Models\RetributionTransactionLine;
use App\Models\RetributionType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sumber baris untuk laporan dinamis "retribusi_upf" (dipanggil dari
 * ReportBuilderService::fetchRawRows()). Mengembalikan array asosiatif
 * polos per baris — bukan Eloquent model — karena setiap baris punya kolom
 * dinamis `retribusi_line_{id}` (satu per jenis retribusi aktif saat
 * request), yang tidak bisa dimodelkan sebagai atribut Eloquent tetap.
 */
class RetributionReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function retribusiUpf(array $filters): Collection
    {
        $activeTypeIds = RetributionType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->pluck('id');

        $transactions = RetributionTransaction::query()
            ->with(['lines', 'branch', 'creator', 'member'])
            // Transaksi yang dibatalkan sudah tercermin di jurnal pembalik dan
            // tidak lagi jadi pendapatan — dikeluarkan dari laporan supaya
            // nilainya konsisten dengan Rekap Pendapatan UPF.
            ->whereNull('cancelled_at')
            ->when($filters['branch_id'] ?? null, fn ($q, $branchId) => $q->where('branch_id', $branchId))
            ->when($filters['period_start'] ?? null, fn ($q, $date) => $q->where('transaction_date', '>=', $date))
            ->when($filters['period_end'] ?? null, fn ($q, $date) => $q->where('transaction_date', '<=', $date))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        return $transactions->map(function (RetributionTransaction $transaction) use ($activeTypeIds) {
            $linesByTypeId = $transaction->lines->keyBy('retribution_type_id');

            $row = [
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                'transaction_number' => $transaction->transaction_number,
                'payer_name' => $transaction->payer_name,
                'member_number' => $transaction->member?->member_number,
                'total_amount' => (float) $transaction->total_amount,
                'payment_method' => $transaction->payment_method,
                'branch_name' => $transaction->branch->name,
                'created_by_name' => $transaction->creator->name,
                'description' => $transaction->description,
            ];

            foreach ($activeTypeIds as $typeId) {
                $row["retribusi_line_{$typeId}"] = (float) ($linesByTypeId[$typeId]->amount ?? 0);
            }

            return $row;
        });
    }

    /**
     * Rekonsiliasi Laporan Harian UPF dengan Kartu Buku Besar kas (permintaan
     * Okt 2026: laporan 13 Okt selisih Rp 6.500 dari Buku Besar).
     *
     * Laporan harian hanya memuat transaksi yang TIDAK batal bertanggal hari
     * itu, sedangkan Buku Besar mencatat jurnal asal pada tanggal transaksi
     * dan jurnal pembaliknya pada tanggal PEMBATALAN. Bila keduanya jatuh di
     * hari yang sama, saling meniadakan; bila berbeda hari, muncul selisih:
     *  - "masuk": transaksi bertanggal hari ini yang dibatalkan di hari lain —
     *    jurnal asalnya tetap ada di Buku Besar hari ini (+);
     *  - "batal": pembatalan hari ini atas transaksi bertanggal lain — jurnal
     *    pembaliknya mengurangi Buku Besar hari ini (−).
     * Tanggal yang dipakai adalah tanggal JURNAL (entry_date), persis yang
     * dibaca Kartu Buku Besar.
     *
     * @return array{masuk: Collection<int, array<string, mixed>>, batal: Collection<int, array<string, mixed>>, koreksi: Collection<int, array<string, mixed>>, total_masuk: float, total_batal: float, total_koreksi: float}
     */
    public function rekonsiliasiHarian(?int $branchId, string $date): array
    {
        return $this->rekonsiliasiPeriode($branchId, $date, $date);
    }

    /**
     * Rekonsiliasi yang sama untuk satu periode (dipakai Rekap Pendapatan
     * UPF): "masuk" = transaksi dalam periode yang dibatalkan SETELAH
     * periode berakhir, "batal" = pembatalan dalam periode atas transaksi
     * SEBELUM periode dimulai, "koreksi" = jurnal koreksi dalam periode.
     * Pembatalan yang asal & pembaliknya sama-sama di dalam periode saling
     * meniadakan sehingga tidak dimunculkan.
     *
     * @return array{masuk: Collection<int, array<string, mixed>>, batal: Collection<int, array<string, mixed>>, koreksi: Collection<int, array<string, mixed>>, total_masuk: float, total_batal: float, total_koreksi: float}
     */
    public function rekonsiliasiPeriode(?int $branchId, string $start, string $end): array
    {
        $dalam = fn (string $kolom) => fn ($q) => $q->whereDate($kolom, '>=', $start)->whereDate($kolom, '<=', $end);
        $luar = fn (string $kolom) => fn ($q) => $q->whereDate($kolom, '<', $start)->orWhereDate($kolom, '>', $end);

        // Query builder polos: cabang sudah ditegakkan lewat $branchId oleh
        // controller (resolveBranchId), dan global scope cabang memakai nama
        // tabel penuh yang tidak cocok dengan alias di bawah.
        $dasar = fn () => DB::table('retribution_transactions as r')
            ->join('journal_entries as asal', 'asal.id', '=', 'r.journal_entry_id')
            ->join('journal_entries as balik', 'balik.id', '=', 'r.reversal_journal_entry_id')
            ->whereNotNull('r.cancelled_at')
            ->when($branchId, fn ($q, $id) => $q->where('r.branch_id', $id))
            ->select([
                'r.transaction_number', 'r.payer_name', 'r.total_amount', 'r.cancellation_reason',
                'asal.entry_date as tanggal_transaksi', 'balik.entry_date as tanggal_batal',
            ])
            ->orderBy('r.id');

        $petakan = fn ($baris) => [
            'transaction_number' => $baris->transaction_number,
            'payer_name' => $baris->payer_name,
            'total_amount' => (float) $baris->total_amount,
            'cancellation_reason' => $baris->cancellation_reason,
            'tanggal_transaksi' => substr((string) $baris->tanggal_transaksi, 0, 10),
            'tanggal_batal' => substr((string) $baris->tanggal_batal, 0, 10),
        ];

        $masuk = $dasar()->where($dalam('asal.entry_date'))->where($luar('balik.entry_date'))->get()->map($petakan);
        $batal = $dasar()->where($dalam('balik.entry_date'))->where($luar('asal.entry_date'))->get()->map($petakan);

        // Koreksi di luar pembatalan resmi: pembalik berlebih atas jurnal
        // transaksi retribusi (mis. #13175, klik ganda) bernilai minus, dan
        // jurnal yang menetralkannya (mis. #15579) bernilai plus.
        $berlebih = DB::table('journal_entries as x')
            ->join('retribution_transactions as r', 'r.journal_entry_id', '=', 'x.reversal_of_entry_id')
            ->where(fn ($q) => $q->whereNull('r.reversal_journal_entry_id')->orWhereColumn('r.reversal_journal_entry_id', '<>', 'x.id'))
            ->when($branchId, fn ($q, $id) => $q->where('r.branch_id', $id));
        $koreksi = (clone $berlebih)->where($dalam('x.entry_date'))
            ->get(['x.id', 'x.description', 'r.transaction_number', 'r.payer_name', 'r.total_amount'])
            ->map(fn ($k) => ['journal_id' => $k->id, 'description' => $k->description, 'transaction_number' => $k->transaction_number, 'payer_name' => $k->payer_name, 'amount' => -1 * (float) $k->total_amount])
            ->merge(
                DB::table('journal_entries as y')->joinSub((clone $berlebih)->select(['x.id as berlebih_id', 'r.transaction_number', 'r.payer_name', 'r.total_amount']), 'b', 'b.berlebih_id', '=', 'y.reversal_of_entry_id')
                    ->where($dalam('y.entry_date'))
                    ->get(['y.id', 'y.description', 'b.transaction_number', 'b.payer_name', 'b.total_amount'])
                    ->map(fn ($k) => ['journal_id' => $k->id, 'description' => $k->description, 'transaction_number' => $k->transaction_number, 'payer_name' => $k->payer_name, 'amount' => (float) $k->total_amount])
            )->values();

        return [
            'masuk' => $masuk,
            'batal' => $batal,
            'koreksi' => $koreksi,
            'total_masuk' => round((float) $masuk->sum('total_amount'), 2),
            'total_batal' => round((float) $batal->sum('total_amount'), 2),
            'total_koreksi' => round((float) $koreksi->sum('amount'), 2),
        ];
    }

    /**
     * Rekap ringkas periode: total kas masuk + breakdown per jenis
     * retribusi (nama, jumlah baris terpakai, total nilai) — dipakai oleh
     * cetakan portrait "Rekap Pendapatan UPF" (periode harian s/d bulanan).
     *
     * Sumbernya `retribution_transaction_lines` (yang di-snapshot per
     * transaksi) di-join ke header supaya bisa difilter branch/tanggal;
     * transaksi yang dibatalkan (`cancelled_at IS NOT NULL`) DIKELUARKAN
     * — jumlahnya sudah tercermin di jurnal pembalik dan tidak lagi
     * jadi pendapatan.
     *
     * @param  array{branch_id?: int|null, period_start: string, period_end: string}  $filters
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     branch_id: int|null,
     *     transaction_count: int,
     *     transaction_count_umum: int,
     *     transaction_count_anggota: int,
     *     total_cash_in: float,
     *     breakdown: array<int, array{name: string, count: int, total: float}>,
     * }
     */
    public function rekapPendapatan(array $filters): array
    {
        $branchId = $filters['branch_id'] ?? null;
        $start = $filters['period_start'];
        $end = $filters['period_end'];

        $baseTx = RetributionTransaction::query()
            ->whereNull('cancelled_at')
            ->where('transaction_date', '>=', $start)
            ->where('transaction_date', '<=', $end)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $totalCashIn = (float) (clone $baseTx)->sum('total_amount');
        $transactionCount = (clone $baseTx)->count();
        $transactionCountUmum = (clone $baseTx)->where('payer_type', 'umum')->count();
        $transactionCountAnggota = (clone $baseTx)->where('payer_type', 'anggota')->count();

        $counted = RetributionTransactionLine::query()
            ->join('retribution_transactions', 'retribution_transactions.id', '=', 'retribution_transaction_lines.retribution_transaction_id')
            ->whereNull('retribution_transactions.cancelled_at')
            ->where('retribution_transactions.transaction_date', '>=', $start)
            ->where('retribution_transactions.transaction_date', '<=', $end)
            ->when($branchId, fn ($q) => $q->where('retribution_transactions.branch_id', $branchId))
            ->selectRaw('retribution_transaction_lines.retribution_type_name as name, COUNT(*) as line_count, SUM(retribution_transaction_lines.amount) as total')
            ->groupBy('retribution_transaction_lines.retribution_type_name')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'count' => (int) $row->line_count,
                'total' => (float) $row->total,
            ])
            ->keyBy('name');

        // Setiap jenis retribusi yang aktif harus tetap tampil — kalau
        // periode ini belum ada transaksi untuk jenisnya, tampilkan
        // barisnya dengan count 0 & total Rp 0 (permintaan pengurus:
        // rekap harus menunjukkan seluruh jenis retribusi yang berlaku,
        // bukan hanya yang bertransaksi). Nama diambil dari master supaya
        // ejaan konsisten dengan pengaturan terkini.
        $activeTypeNames = RetributionType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('name');

        $nonZero = $counted->values()->sortByDesc('total')->values();

        $zeroRows = $activeTypeNames
            ->reject(fn (string $name) => $counted->has($name))
            ->map(fn (string $name) => ['name' => $name, 'count' => 0, 'total' => 0.0])
            ->values();

        $breakdown = $nonZero->concat($zeroRows)->values()->all();

        return [
            'period_start' => $start,
            'period_end' => $end,
            'branch_id' => $branchId,
            'transaction_count' => $transactionCount,
            'transaction_count_umum' => $transactionCountUmum,
            'transaction_count_anggota' => $transactionCountAnggota,
            'total_cash_in' => $totalCashIn,
            'breakdown' => $breakdown,
        ];
    }
}
