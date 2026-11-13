<?php

namespace App\Services\Loans;

use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanBranchRepair;
use App\Models\LoanRepayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Memindahkan kegiatan pinjaman yang terlanjur tercatat di cabang lain ke
 * cabang yang semestinya.
 *
 * Kenapa ini perlu ada sebagai fitur, bukan sekali jalan lewat SQL: cabang
 * pinjaman POS "hutang" diturunkan dari cabang penjualannya, jadi selama
 * toko melayani kredit di Pusat, angsuran baru akan terus mendarat di
 * Pusat. Perbaikan sekali jalan akan usang sendiri; layar yang bisa
 * dijalankan ulang tidak.
 *
 * Yang diubah HANYA kolom branch_id. Nominal, akun, tanggal, dan pasangan
 * debet/kredit tidak disentuh sama sekali — buktinya diperiksa sendiri oleh
 * apply() lewat invarian di bawah, dan pemindahan dibatalkan kalau meleset.
 */
class LoanBranchRepairService
{
    /**
     * Pinjaman yang lahir dari transaksi POS "hutang" — dikenali dari tidak
     * punya jurnal pencairan sendiri (jurnalnya menyatu di jurnal penjualan
     * POS). Pinjaman biasa punya jurnal pencairannya sendiri dan cabangnya
     * sudah ditentukan LoanBranchResolver saat pencairan, jadi tidak pernah
     * ikut dipindah di sini.
     *
     * @return array<int, int>
     */
    private function idPinjamanPos(): array
    {
        return Loan::query()
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('journal_entries')
                    ->whereColumn('journal_entries.source_id', 'loans.id')
                    ->where('journal_entries.source_type', Loan::class);
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Baris apa saja yang akan pindah, tanpa mengubah apa pun.
     *
     * @return array{loans: Collection, repayments: Collection, entries: Collection}
     */
    public function sasaran(int $branchId): array
    {
        $idPos = $this->idPinjamanPos();

        return [
            'loans' => Loan::query()
                ->whereIn('id', $idPos ?: [0])
                ->where('branch_id', '!=', $branchId)
                ->get(['id', 'branch_id']),

            'repayments' => LoanRepayment::query()
                ->where('branch_id', '!=', $branchId)
                ->get(['id', 'branch_id']),

            'entries' => JournalEntry::query()
                ->where('source_type', LoanRepayment::class)
                ->where('branch_id', '!=', $branchId)
                ->get(['id', 'branch_id']),
        ];
    }

    /**
     * Ringkasan untuk layar: berapa baris per tabel, dan dari cabang mana.
     *
     * @return array<string, mixed>
     */
    public function pratinjau(int $branchId): array
    {
        $sasaran = $this->sasaran($branchId);

        $namaCabang = Branch::query()->pluck('name', 'id');

        $asal = [];
        foreach ($sasaran as $rows) {
            foreach ($rows as $r) {
                $kunci = $r->branch_id ?? 0;
                $asal[$kunci] = ($asal[$kunci] ?? 0) + 1;
            }
        }
        arsort($asal);

        return [
            'loans' => $sasaran['loans']->count(),
            'repayments' => $sasaran['repayments']->count(),
            'entries' => $sasaran['entries']->count(),
            'total' => array_sum(array_map(fn ($r) => $r->count(), $sasaran)),
            'asal' => collect($asal)->map(fn ($jml, $id) => [
                'cabang' => $namaCabang[$id] ?? 'Tanpa cabang',
                'jumlah' => $jml,
            ])->values(),
            // Pendapatan yang selama ini tidak masuk laba rugi cabang tujuan.
            // Inilah alasan sebenarnya layar ini ada; jumlah baris hanya
            // ukuran pekerjaannya.
            'pendapatan' => $this->pendapatanTertinggal($branchId),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function pendapatanTertinggal(int $branchId)
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as j', 'j.id', '=', 'jl.journal_entry_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jl.chart_of_account_id')
            ->where('j.source_type', LoanRepayment::class)
            ->where('j.branch_id', '!=', $branchId)
            ->where('coa.type', 'PENDAPATAN')
            ->groupBy('coa.code', 'coa.name')
            ->select('coa.code', 'coa.name', DB::raw('SUM(jl.credit - jl.debit) as neto'))
            ->get();
    }

    /**
     * Pindahkan, lalu buktikan pembukuannya tidak berubah.
     *
     * Invarian yang diperiksa: jumlah jurnal, jumlah baris jurnal, total
     * debet, dan total kredit harus sama persis seperti sebelum pemindahan,
     * dan debet harus tetap sama dengan kredit. Kalau salah satu meleset,
     * seluruh transaksi dibatalkan — lebih baik gagal daripada meninggalkan
     * pembukuan yang tidak seimbang.
     */
    public function jalankan(int $branchId, int $userId): LoanBranchRepair
    {
        $sasaran = $this->sasaran($branchId);
        $total = array_sum(array_map(fn ($r) => $r->count(), $sasaran));

        if ($total === 0) {
            throw new RuntimeException('Tidak ada baris yang perlu dipindahkan — semuanya sudah di cabang ini.');
        }

        $sebelum = $this->potretPembukuan();

        return DB::transaction(function () use ($sasaran, $branchId, $userId, $sebelum) {
            $payload = [];

            foreach ($sasaran as $nama => $rows) {
                $payload[$nama] = $rows->map(fn ($r) => ['id' => $r->id, 'branch_id' => $r->branch_id])->all();
            }

            $this->pindahkan(Loan::class, $sasaran['loans']->pluck('id')->all(), $branchId);
            $this->pindahkan(LoanRepayment::class, $sasaran['repayments']->pluck('id')->all(), $branchId);
            $this->pindahkan(JournalEntry::class, $sasaran['entries']->pluck('id')->all(), $branchId);

            $this->pastikanPembukuanUtuh($sebelum);

            return LoanBranchRepair::query()->create([
                'target_branch_id' => $branchId,
                'performed_by' => $userId,
                'loans_moved' => $sasaran['loans']->count(),
                'repayments_moved' => $sasaran['repayments']->count(),
                'entries_moved' => $sasaran['entries']->count(),
                'payload' => $payload,
            ]);
        });
    }

    /**
     * Kembalikan setiap baris ke cabang ASALNYA SENDIRI, bukan ke satu
     * cabang seragam — baris yang dipindah bisa berasal dari cabang yang
     * berbeda-beda.
     */
    public function batalkan(LoanBranchRepair $repair, int $userId): LoanBranchRepair
    {
        if ($repair->reverted_at !== null) {
            throw new RuntimeException('Perbaikan ini sudah dibatalkan sebelumnya.');
        }

        $sebelum = $this->potretPembukuan();

        return DB::transaction(function () use ($repair, $userId, $sebelum) {
            $kelas = [
                'loans' => Loan::class,
                'repayments' => LoanRepayment::class,
                'entries' => JournalEntry::class,
            ];

            foreach ($repair->payload as $nama => $rows) {
                // Dikelompokkan per cabang asal supaya satu UPDATE melayani
                // banyak baris, alih-alih satu kueri per baris.
                $perCabang = [];
                foreach ($rows as $r) {
                    $perCabang[$r['branch_id'] ?? 0][] = $r['id'];
                }

                foreach ($perCabang as $cabangLama => $ids) {
                    $this->pindahkan($kelas[$nama], $ids, $cabangLama ?: null);
                }
            }

            $this->pastikanPembukuanUtuh($sebelum);

            $repair->update(['reverted_at' => now(), 'reverted_by' => $userId]);

            return $repair->fresh();
        });
    }

    /**
     * @param  class-string<Model>  $kelas
     * @param  array<int, int>  $ids
     */
    private function pindahkan(string $kelas, array $ids, ?int $branchId): void
    {
        foreach (array_chunk($ids, 500) as $potong) {
            $kelas::query()->withoutGlobalScopes()->whereIn('id', $potong)->update(['branch_id' => $branchId]);
        }
    }

    /** @return array<string, float|int> */
    private function potretPembukuan(): array
    {
        return [
            'entries' => DB::table('journal_entries')->count(),
            'lines' => DB::table('journal_lines')->count(),
            'debit' => (float) DB::table('journal_lines')->sum('debit'),
            'credit' => (float) DB::table('journal_lines')->sum('credit'),
        ];
    }

    /** @param  array<string, float|int>  $sebelum */
    private function pastikanPembukuanUtuh(array $sebelum): void
    {
        $sesudah = $this->potretPembukuan();

        $utuh = $sebelum['entries'] === $sesudah['entries']
            && $sebelum['lines'] === $sesudah['lines']
            && abs($sebelum['debit'] - $sesudah['debit']) < 0.01
            && abs($sebelum['credit'] - $sesudah['credit']) < 0.01
            && abs($sesudah['debit'] - $sesudah['credit']) < 0.01;

        if (! $utuh) {
            throw new RuntimeException(
                'Pemindahan dibatalkan: pembukuan berubah, padahal yang disentuh hanya kolom cabang. '
                .'Tidak ada data yang tersimpan.'
            );
        }
    }
}
