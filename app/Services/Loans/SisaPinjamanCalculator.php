<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\OpeningBalanceLoan;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Collection;

/**
 * Sisa Pinjaman (sisa POKOK) untuk laporan & cetakan — dihitung ulang dari
 * transaksi setiap kali laporan dibuat, BUKAN dibaca dari kolom
 * loan_repayments.balance_after.
 *
 * Kenapa balance_after tidak bisa dipakai (laporan 28 Sep 2026, pinjaman
 * MIGRASI-1433 a.n. WATI: bayar Rp 34.546.100 = Pokok 31.150.000 + Jasa
 * 3.296.100 + Denda 100.000, tapi Sisa Pinjaman tampil Rp 100.000):
 * balance_after = sisa tagihan di loan_schedules (pokok + SELURUH jasa
 * jadwal yang belum dibayar) dikurangi Pokok+Jasa yang diinput staf. Staf
 * memecah total tagihan itu sendiri menjadi Pokok/Jasa/Denda, jadi bagian
 * yang ia sebut "denda" tidak pernah mengurangi jadwal dan tertinggal
 * sebagai "sisa pinjaman" palsu sebesar denda itu. Di sisi lain, jasa
 * jadwal yang belum jatuh tempo ikut terhitung sebagai "sisa pinjaman".
 *
 * Definisi di sini mengikuti buku besar — satu-satunya angka yang juga
 * dipakai jurnal (akun Piutang Pinjaman hanya dikredit sebesar
 * principal_portion; denda ke akun Piutang Denda, jasa ke Pendapatan Jasa):
 *
 *   Sisa Pinjaman = Pokok Awal − Σ principal_portion angsuran yang tidak
 *                   dibatalkan, berurutan menurut tanggal bayar.
 *
 * Denda dan jasa TIDAK PERNAH ikut dihitung.
 *
 *   - Pokok Awal pinjaman yang dicairkan di aplikasi = principal_amount.
 *   - Pokok Awal pinjaman hasil migrasi saldo awal = sisa pokok per tanggal
 *     cutoff (opening_balance_loans.outstanding_principal) — angka yang sama
 *     dengan jurnal pembukaan. principal_amount pinjaman migrasi adalah
 *     plafon awal di sistem lama, bukan posisi piutang saat cutoff.
 *   - Baris riwayat hasil import dari sistem lama (migrated_at terisi) adalah
 *     arsip, bukan transaksi (lihat LoanRepaymentHistoryImportService): sisa
 *     yang ditampilkan adalah angka sistem lama apa adanya, dan baris itu
 *     tidak mengurangi Pokok Awal (posisi cutoff sudah memperhitungkannya).
 *
 * Murni baca: tidak ada satu pun kolom/baris database yang diubah.
 */
class SisaPinjamanCalculator
{
    /**
     * Sisa pinjaman SETELAH tiap angsuran.
     *
     * Semua angsuran pinjaman-pinjaman itu dibaca tanpa BranchScope supaya
     * saldo berjalannya utuh walau pengguna hanya boleh melihat sebagian
     * baris; yang ditampilkan tetap hanya baris yang boleh ia lihat.
     *
     * @param  iterable<int>  $loanIds
     * @return array<int, float> repayment_id => sisa pokok setelah angsuran itu
     */
    public function setelahTiapAngsuran(iterable $loanIds): array
    {
        $hasil = [];

        foreach ($this->hitung($loanIds) as $perPinjaman) {
            $hasil += $perPinjaman['setelah'];
        }

        return $hasil;
    }

    /**
     * Sisa pinjaman saat ini, per pinjaman.
     *
     * @param  iterable<int>  $loanIds
     * @return array<int, float> loan_id => sisa pokok
     */
    public function saatIni(iterable $loanIds): array
    {
        return array_map(fn (array $perPinjaman) => $perPinjaman['sisa'], $this->hitung($loanIds));
    }

    /**
     * @param  iterable<int>  $loanIds
     * @return array<int, array{sisa: float, setelah: array<int, float>}>
     */
    private function hitung(iterable $loanIds): array
    {
        $loanIds = collect($loanIds)->filter()->unique()->values();

        if ($loanIds->isEmpty()) {
            return [];
        }

        $loans = $loanIds->chunk(1000)->flatMap(
            fn (Collection $ids) => Loan::query()
                ->withoutGlobalScope(BranchScope::class)
                ->whereIn('id', $ids->all())
                ->get(['id', 'member_id', 'loan_number', 'principal_amount']),
        );

        $pokokAwal = array_map(fn (array $posisi) => $posisi['pokok'], $this->posisiAwal($loans));

        $angsuran = $loanIds->chunk(1000)->flatMap(
            fn (Collection $ids) => LoanRepayment::query()
                ->withoutGlobalScope(BranchScope::class)
                ->whereIn('loan_id', $ids->all())
                ->get(['id', 'loan_id', 'principal_portion', 'balance_after', 'paid_at', 'created_at', 'migrated_at', 'cancelled_at']),
        )->groupBy('loan_id');

        $hasil = [];

        foreach ($loans as $loan) {
            $sisa = $pokokAwal[$loan->id];
            $setelah = [];

            $urut = ($angsuran[$loan->id] ?? collect())->sortBy([
                fn (LoanRepayment $a, LoanRepayment $b) => $a->paidOn()->toDateString() <=> $b->paidOn()->toDateString(),
                fn (LoanRepayment $a, LoanRepayment $b) => $a->id <=> $b->id,
            ]);

            foreach ($urut as $repayment) {
                if ($repayment->migrated_at !== null) {
                    $setelah[$repayment->id] = (float) $repayment->balance_after;

                    continue;
                }

                if (! $repayment->isCancelled()) {
                    $sisa = round($sisa - (float) $repayment->principal_portion, 2);
                }

                $setelah[$repayment->id] = $sisa;
            }

            $hasil[$loan->id] = ['sisa' => $sisa, 'setelah' => $setelah];
        }

        return $hasil;
    }

    /**
     * Posisi awal tiap pinjaman menurut buku besar: pokok awal, dan — untuk
     * pinjaman migrasi — sisa jasa per tanggal cutoff (null untuk pinjaman
     * yang dicairkan di aplikasi: jasanya ditentukan jadwal pencairan).
     *
     * Pinjaman migrasi dikenali dari cara OpeningBalanceLockService::
     * materializeLoans() menomorinya: nomor pinjaman lama
     * (external_loan_number) kalau diisi, kalau tidak "MIGRASI-{id baris}".
     * Nomor lama dicocokkan bersama anggotanya; kalau tetap tidak tunggal,
     * pinjaman itu diperlakukan seperti pinjaman biasa.
     *
     * @param  Collection<int, Loan>  $loans
     * @return array<int, array{pokok: float, jasa: ?float, migrasi: bool}> per loan_id
     */
    public function posisiAwal(Collection $loans): array
    {
        $saldoAwal = OpeningBalanceLoan::query()
            ->whereHas('batch', fn ($q) => $q->where('status', 'locked'))
            ->get(['id', 'member_id', 'external_loan_number', 'outstanding_principal', 'outstanding_interest']);

        $perId = $saldoAwal->keyBy('id');
        $perNomorLama = $saldoAwal
            ->filter(fn (OpeningBalanceLoan $row) => $row->external_loan_number !== null && $row->external_loan_number !== '')
            ->groupBy(fn (OpeningBalanceLoan $row) => $row->member_id.'|'.$row->external_loan_number);

        $hasil = [];

        foreach ($loans as $loan) {
            $baris = null;

            if (preg_match('/^MIGRASI-(\d+)$/', (string) $loan->loan_number, $cocok) === 1) {
                $baris = $perId->get((int) $cocok[1]);
            } else {
                $kandidat = $perNomorLama->get($loan->member_id.'|'.$loan->loan_number);
                $baris = $kandidat !== null && $kandidat->count() === 1 ? $kandidat->first() : null;
            }

            $hasil[$loan->id] = [
                'pokok' => round((float) ($baris?->outstanding_principal ?? $loan->principal_amount), 2),
                'jasa' => $baris === null ? null : round((float) $baris->outstanding_interest, 2),
                'migrasi' => $baris !== null,
            ];
        }

        return $hasil;
    }
}
