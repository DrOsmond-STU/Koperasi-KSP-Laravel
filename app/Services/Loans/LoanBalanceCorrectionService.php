<?php

namespace App\Services\Loans;

use App\Exceptions\Loans\LoanRepaymentException;
use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanScheduleAlignment;
use App\Models\OpeningBalanceBatch;
use App\Models\Scopes\BranchScope;
use App\Services\Accounting\CashAccountResolver;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Perbaikan Saldo Pinjaman Anggota (permintaan pemilik 11 Nov 2026).
 *
 * Banyak saldo pinjaman anggota salah karena dua hal:
 *  1. Angsuran diinput tidak urut tanggal / dengan tanggal yang keliru
 *     (contoh 117-0151-01053 MUJAHIDIN: angsuran Rp 100.000 diinput 2 Okt
 *     tetapi bertanggal 1 Jul, sehingga saldo per 31 Agt ikut berkurang).
 *  2. Pembatalan angsuran mengembalikan jadwal (loan_schedules) menurut
 *     alokasi yang direkam saat input — yang sendirinya bisa sudah salah
 *     karena urutan input — sehingga jadwal & status "lunas" menyimpang dari
 *     buku besar (contoh 117-0151-01047 MASKURI).
 *
 * Acuan saldo SELALU buku besar: pokok awal (saldo awal migrasi, atau plafon
 * untuk pinjaman yang dicairkan di aplikasi) dikurangi porsi pokok angsuran
 * yang tidak dibatalkan, diurutkan menurut TANGGAL TRANSAKSI; jasa sama
 * caranya dengan jasa awal (sisa jasa saldo awal, atau total jasa jadwal).
 * Sama persis dengan SisaPinjamanCalculator & LoanScheduleAlignmentService.
 *
 * Koreksi dilakukan PER TRANSAKSI, tidak pernah mengubah baris yang sudah
 * diposting (JournalEngine append-only, LED-06):
 *  - angsuran yang salah ditandai dibatalkan dan jurnalnya dibalik
 *    (jurnal pembalik bertanggal sama dengan jurnal asal);
 *  - angsuran pengganti dicatat dengan tanggal / pokok / jasa / denda yang
 *    benar, jurnalnya bertanggal transaksi yang benar, akun kas penerimanya
 *    sama dengan jurnal asal;
 *  - jadwal angsuran & status lunas pinjaman itu lalu diselaraskan ulang
 *    dengan buku besar lewat LoanScheduleAlignmentService (bisa dibatalkan
 *    dari menu Penyelarasan Jadwal);
 *  - jejaknya dicatat di audit_logs (aksi "koreksi_angsuran").
 * Semua langkah dalam SATU transaksi database: gagal satu, batal semua.
 */
class LoanBalanceCorrectionService
{
    private const TOLERANSI = 0.5;

    /** Selisih tanggal input vs tanggal transaksi yang dianggap mencurigakan. */
    public const HARI_INPUT_TERLAMBAT = 45;

    public function __construct(
        private readonly SisaPinjamanCalculator $sisaPinjaman,
        private readonly LoanScheduleAlignmentService $penyelarasan,
        private readonly JournalEngine $journalEngine,
        private readonly CashAccountResolver $cashAccounts,
    ) {}

    /**
     * Kartu pinjaman menurut buku besar: saldo awal lalu setiap angsuran
     * (termasuk yang dibatalkan, untuk ditampilkan) urut tanggal transaksi,
     * dengan sisa pokok & sisa jasa berjalan, serta saldo per $per.
     *
     * @return array<string, mixed>
     */
    public function kartu(Loan $loan, ?Carbon $per = null): array
    {
        $loan->loadMissing(['member', 'loanProduct', 'schedules']);
        $per = ($per ?? now())->copy()->endOfDay();

        $posisi = $this->sisaPinjaman->posisiAwal(collect([$loan]))[$loan->id];
        $jasaAwal = $posisi['migrasi'] ? (float) $posisi['jasa'] : round((float) $loan->schedules->sum('interest_amount'), 2);
        $tanggalAwal = $this->tanggalAwal($loan, $posisi['migrasi']);

        $angsuran = LoanRepayment::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('loan_id', $loan->id)
            ->whereNull('migrated_at')
            ->with(['createdBy'])
            ->get()
            ->sortBy([
                fn (LoanRepayment $a, LoanRepayment $b) => $this->tanggal($a) <=> $this->tanggal($b),
                fn (LoanRepayment $a, LoanRepayment $b) => $a->id <=> $b->id,
            ])
            ->values();

        $sisaPokok = $posisi['pokok'];
        $sisaJasa = $jasaAwal;
        $saldoPer = ['pokok' => $sisaPokok, 'jasa' => $sisaJasa];
        $baris = [];
        $total = ['jumlah' => 0.0, 'pokok' => 0.0, 'jasa' => 0.0, 'denda' => 0.0];

        foreach ($angsuran as $r) {
            $tanggal = $this->tanggal($r);
            $batal = $r->isCancelled();

            if (! $batal) {
                $sisaPokok = round($sisaPokok - (float) $r->principal_portion, 2);
                $sisaJasa = round($sisaJasa - (float) $r->interest_portion, 2);
                $total['jumlah'] += (float) $r->amount;
                $total['pokok'] += (float) $r->principal_portion;
                $total['jasa'] += (float) $r->interest_portion;
                $total['denda'] += (float) $r->penalty_portion;

                if (Carbon::parse($tanggal)->lte($per)) {
                    $saldoPer = ['pokok' => $sisaPokok, 'jasa' => $sisaJasa];
                }
            }

            $catatan = [];
            if (! $batal && $this->terlaluDini($tanggal, $tanggalAwal, $posisi['migrasi'])) {
                $catatan[] = $posisi['migrasi']
                    ? 'Bertanggal tidak sesudah saldo awal ('.$tanggalAwal->translatedFormat('d M Y').'), padahal pembayaran s/d tanggal itu sudah termasuk saldo awal — cek tanggal transaksinya.'
                    : 'Bertanggal sebelum tanggal pencairan ('.$tanggalAwal->translatedFormat('d M Y').') — cek tanggal transaksinya.';
            }
            $jeda = Carbon::parse($tanggal)->startOfDay()->diffInDays($r->created_at->copy()->startOfDay(), false);
            if (! $batal && $jeda > self::HARI_INPUT_TERLAMBAT) {
                $catatan[] = 'Diinput '.(int) $jeda.' hari setelah tanggal transaksinya — cek tanggalnya.';
            }
            if (! $batal && ($sisaPokok < -self::TOLERANSI || $sisaJasa < -self::TOLERANSI)) {
                $catatan[] = 'Setelah angsuran ini sisa '.($sisaPokok < -self::TOLERANSI ? 'pokok' : 'jasa').' menjadi minus.';
            }

            $baris[] = [
                'repayment' => $r,
                'tanggal' => $tanggal,
                'batal' => $batal,
                'sisa_pokok' => $batal ? null : $sisaPokok,
                'sisa_jasa' => $batal ? null : $sisaJasa,
                'catatan' => $catatan,
            ];
        }

        $rencana = $this->penyelarasan->rencanaUntuk($loan);

        $masalah = [];
        if ($sisaPokok < -self::TOLERANSI) {
            $masalah[] = 'Sisa pokok minus (pokok terbayar lebih Rp '.number_format(abs($sisaPokok), 0, ',', '.').').';
        }
        if ($sisaJasa < -self::TOLERANSI) {
            $masalah[] = 'Sisa jasa minus (jasa terbayar lebih Rp '.number_format(abs($sisaJasa), 0, ',', '.').').';
        }
        if ($loan->status === 'lunas' && ($sisaPokok > self::TOLERANSI || $sisaJasa > self::TOLERANSI)) {
            $masalah[] = 'Status LUNAS padahal menurut buku besar masih ada sisa.';
        }
        if ($rencana !== null && $rencana['perlu']) {
            $masalah[] = 'Jadwal angsuran / status belum selaras dengan buku besar.';
        }
        $jumlahCatatan = collect($baris)->filter(fn ($b) => $b['catatan'] !== [])->count();
        if ($jumlahCatatan > 0) {
            $masalah[] = $jumlahCatatan.' angsuran perlu dicek (lihat kolom Catatan).';
        }

        return [
            'loan' => $loan,
            'migrasi' => $posisi['migrasi'],
            'tanggal_awal' => $tanggalAwal,
            'pokok_awal' => $posisi['pokok'],
            'jasa_awal' => $jasaAwal,
            'baris' => $baris,
            'total' => $total,
            'sisa_pokok' => $sisaPokok,
            'sisa_jasa' => $sisaJasa,
            'per' => $per,
            'saldo_per' => $saldoPer,
            'jadwal' => $rencana,
            'masalah' => $masalah,
        ];
    }

    /**
     * Pinjaman aktif/lunas yang saldonya perlu diperiksa: sisa pokok/jasa
     * minus, lunas padahal bersisa, jadwal tidak selaras, atau ada angsuran
     * bertanggal sebelum saldo awal/pencairan. Urut dari nomor pinjaman.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function daftarBermasalah(?string $cari = null): Collection
    {
        $loans = Loan::query()
            ->whereIn('status', ['dicairkan', 'lunas'])
            ->when($cari, fn ($q) => $q->where(fn ($w) => $w
                ->where('loan_number', 'like', '%'.$cari.'%')
                ->orWhereHas('member', fn ($m) => $m->where('name', 'like', '%'.$cari.'%')->orWhere('member_number', 'like', '%'.$cari.'%'))))
            ->with(['member', 'schedules'])
            ->orderBy('loan_number')
            ->get();

        if ($loans->isEmpty()) {
            return collect();
        }

        $posisi = $this->sisaPinjaman->posisiAwal($loans);
        $rencana = $this->penyelarasan->rencanaBanyak($loans)->keyBy('loan_id');
        $cutoff = $this->cutoffSaldoAwal();

        $bayar = LoanRepayment::query()
            ->withoutGlobalScope(BranchScope::class)
            ->whereIn('loan_id', $loans->pluck('id')->all())
            ->whereNull('cancelled_at')
            ->whereNull('migrated_at')
            ->get(['id', 'loan_id', 'principal_portion', 'interest_portion', 'paid_at', 'transaction_date', 'created_at'])
            ->groupBy('loan_id');

        return $loans->map(function (Loan $loan) use ($posisi, $rencana, $cutoff, $bayar) {
            $p = $posisi[$loan->id];
            $rows = $bayar[$loan->id] ?? collect();
            $jasaAwal = $p['migrasi'] ? (float) $p['jasa'] : round((float) $loan->schedules->sum('interest_amount'), 2);
            $sisaPokok = round($p['pokok'] - (float) $rows->sum('principal_portion'), 2);
            $sisaJasa = round($jasaAwal - (float) $rows->sum('interest_portion'), 2);
            $awal = $p['migrasi'] ? $cutoff : ($loan->disbursed_at ? Carbon::parse($loan->disbursed_at) : null);

            $masalah = [];
            if ($sisaPokok < -self::TOLERANSI) {
                $masalah[] = 'pokok minus';
            }
            if ($sisaJasa < -self::TOLERANSI) {
                $masalah[] = 'jasa minus';
            }
            if ($loan->status === 'lunas' && ($sisaPokok > self::TOLERANSI || $sisaJasa > self::TOLERANSI)) {
                $masalah[] = 'lunas tapi bersisa';
            }
            if (($rencana[$loan->id]['perlu'] ?? false) === true) {
                $masalah[] = 'jadwal tidak selaras';
            }
            if ($awal !== null) {
                $dini = $rows->filter(fn (LoanRepayment $r) => $this->terlaluDini($this->tanggal($r), $awal, $p['migrasi']))->count();
                if ($dini > 0) {
                    $masalah[] = $dini.' angsuran bertanggal sebelum '.($p['migrasi'] ? 'saldo awal' : 'pencairan');
                }
            }
            // Sekadar catatan: input ulang angsuran lama yang dibatalkan
            // memang wajar diinput belakangan, jadi alasan ini sendirian
            // tidak membuat pinjaman masuk daftar (pemindaian produksi 11 Nov
            // 2026: 69 dari 80 pinjaman hanya karena ini).
            $berat = $masalah !== [];
            $terlambat = $rows->filter(fn (LoanRepayment $r) => Carbon::parse($this->tanggal($r))->startOfDay()
                ->diffInDays($r->created_at->copy()->startOfDay(), false) > self::HARI_INPUT_TERLAMBAT)->count();
            if ($terlambat > 0) {
                $masalah[] = $terlambat.' angsuran diinput >'.self::HARI_INPUT_TERLAMBAT.' hari setelah tanggalnya';
            }

            return [
                'loan' => $loan,
                'sisa_pokok' => $sisaPokok,
                'sisa_jasa' => $sisaJasa,
                'masalah' => $masalah,
                'perlu_diperiksa' => $berat,
            ];
        })
            ->filter(fn (array $r) => $cari !== null && $cari !== '' ? true : $r['perlu_diperiksa'])
            ->values();
    }

    /**
     * Koreksi satu angsuran: batalkan yang lama (jurnal dibalik) lalu catat
     * pengganti dengan tanggal/pokok/jasa/denda yang benar, kemudian
     * selaraskan jadwal & status pinjaman dengan buku besar.
     *
     * @param  array{tanggal: string, pokok: float|string, jasa: float|string, denda: float|string, alasan: string}  $data
     */
    public function koreksi(LoanRepayment $asal, array $data, int $userId): LoanRepayment
    {
        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
        $pokok = round((float) $data['pokok'], 2);
        $jasa = round((float) $data['jasa'], 2);
        $denda = round((float) $data['denda'], 2);
        $alasan = trim((string) $data['alasan']);
        $jumlah = round($pokok + $jasa + $denda, 2);

        if ($pokok < 0 || $jasa < 0 || $denda < 0) {
            throw new RuntimeException('Pokok, jasa dan denda tidak boleh minus.');
        }
        // Pokok + jasa + denda = 0 berarti transaksi ini seharusnya tidak
        // ada: angsuran dibatalkan (jurnalnya dibalik) TANPA pengganti.
        $tanpaPengganti = $jumlah < 0.005;
        if ($tanggal->isAfter(now()->endOfDay())) {
            throw new RuntimeException('Tanggal transaksi tidak boleh di masa depan.');
        }
        if ($alasan === '') {
            throw new RuntimeException('Alasan koreksi wajib diisi.');
        }

        return DB::transaction(function () use ($asal, $tanggal, $pokok, $jasa, $denda, $jumlah, $alasan, $userId, $tanpaPengganti) {
            $loan = Loan::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($asal->loan_id);
            $lama = LoanRepayment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($asal->id);

            if ($lama->migrated_at !== null) {
                throw new RuntimeException('Baris riwayat migrasi (sebelum saldo awal) tidak bisa dikoreksi di sini.');
            }
            if ($lama->isCancelled()) {
                throw LoanRepaymentException::alreadyCancelled();
            }
            if ($lama->journal_entry_id === null) {
                throw LoanRepaymentException::noJournal();
            }

            if ($tanpaPengganti) {
                return $this->batalkanTanpaPengganti($loan, $lama, $alasan, $userId);
            }

            $posisi = $this->sisaPinjaman->posisiAwal(collect([$loan]))[$loan->id];
            $awal = $this->tanggalAwal($loan, $posisi['migrasi']);
            if ($this->terlaluDini($tanggal->toDateString(), $awal, $posisi['migrasi'])) {
                throw new RuntimeException($posisi['migrasi']
                    ? 'Tanggal transaksi harus sesudah tanggal saldo awal ('.$awal->translatedFormat('d M Y').') — pembayaran s/d tanggal itu sudah termasuk saldo awal.'
                    : 'Tanggal transaksi tidak boleh sebelum tanggal pencairan ('.$awal->translatedFormat('d M Y').').');
            }

            $sama = $this->tanggal($lama) === $tanggal->toDateString()
                && abs((float) $lama->principal_portion - $pokok) < 0.005
                && abs((float) $lama->interest_portion - $jasa) < 0.005
                && abs((float) $lama->penalty_portion - $denda) < 0.005;
            if ($sama) {
                throw new RuntimeException('Tidak ada yang berubah — tanggal, pokok, jasa dan denda sama dengan angsuran asal.');
            }

            $product = $loan->loanProduct;
            if ($denda > 0 && $product->coa_penalty_receivable_account_id === null) {
                throw LoanRepaymentException::missingPenaltyAccount();
            }

            /** @var JournalEntry $jurnalAsal */
            $jurnalAsal = JournalEntry::query()->withoutGlobalScopes()->with('lines')->findOrFail($lama->journal_entry_id);
            $akunKas = $this->akunKasAsal($jurnalAsal, $product) ?? $this->cashAccounts->forBranch($loan->branch_id, null)->id;

            $keterangan = "Koreksi angsuran #{$lama->id}: {$alasan}";
            $pembalik = $this->journalEngine->reverse($jurnalAsal, $keterangan, $userId);
            $lama->update([
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancellation_reason' => mb_substr('Dikoreksi (Perbaikan Saldo Pinjaman): '.$alasan, 0, 255),
                'reversal_journal_entry_id' => $pembalik->id,
            ]);

            $baru = LoanRepayment::query()->create([
                'branch_id' => $lama->branch_id,
                'loan_id' => $loan->id,
                'amount' => $jumlah,
                'principal_portion' => $pokok,
                'interest_portion' => $jasa,
                'penalty_portion' => $denda,
                'schedule_allocations' => [],
                'balance_after' => 0,
                'transaction_date' => $tanggal->toDateString(),
                'paid_at' => $tanggal->toDateString(),
                'created_by' => $userId,
                'description' => mb_substr("Pengganti angsuran #{$lama->id} (Perbaikan Saldo Pinjaman): {$alasan}", 0, 255),
            ]);

            $lines = [['chart_of_account_id' => $akunKas, 'debit' => $jumlah, 'credit' => 0]];
            if ($pokok > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_receivable_account_id, 'debit' => 0, 'credit' => $pokok];
            }
            if ($jasa > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_interest_income_account_id, 'debit' => 0, 'credit' => $jasa];
            }
            if ($denda > 0) {
                $lines[] = ['chart_of_account_id' => $product->coa_penalty_receivable_account_id, 'debit' => 0, 'credit' => $denda];
            }

            $jurnalBaru = $this->journalEngine->post([
                'branch_id' => $loan->branch_id,
                'entry_date' => $tanggal->toDateString(),
                'description' => "Pembayaran angsuran {$loan->loan_number} (koreksi atas #{$lama->id})",
                'created_by' => $userId,
                'source' => $baru,
                'lines' => $lines,
            ]);

            $sisa = $this->sisaPinjaman->setelahTiapAngsuran([$loan->id]);
            $baru->update(['journal_entry_id' => $jurnalBaru->id, 'balance_after' => $sisa[$baru->id] ?? 0]);

            AuditLog::query()->create([
                'actor_id' => $userId,
                'actor_role' => auth()->user()?->getRoleNames()->implode(','),
                'branch_id' => $loan->branch_id,
                'action' => 'koreksi_angsuran',
                'auditable_type' => LoanRepayment::class,
                'auditable_id' => $baru->id,
                'before' => [
                    'repayment_id' => $lama->id, 'tanggal' => $this->tanggal($lama), 'jumlah' => (float) $lama->amount,
                    'pokok' => (float) $lama->principal_portion, 'jasa' => (float) $lama->interest_portion,
                    'denda' => (float) $lama->penalty_portion, 'jurnal' => $jurnalAsal->id, 'jurnal_pembalik' => $pembalik->id,
                ],
                'after' => [
                    'repayment_id' => $baru->id, 'tanggal' => $tanggal->toDateString(), 'jumlah' => $jumlah,
                    'pokok' => $pokok, 'jasa' => $jasa, 'denda' => $denda, 'jurnal' => $jurnalBaru->id, 'alasan' => $alasan,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            $this->selaraskanBilaPerlu($loan, $userId);

            return $baru->fresh();
        });
    }

    /**
     * Angsuran yang seharusnya tidak pernah ada (mis. input ganda / salah
     * pinjaman): tandai dibatalkan dan balik jurnalnya — tanpa angsuran
     * pengganti — lalu selaraskan jadwal & status. Dipanggil dari koreksi()
     * di dalam transaksinya, dengan pinjaman & angsuran sudah terkunci.
     */
    private function batalkanTanpaPengganti(Loan $loan, LoanRepayment $lama, string $alasan, int $userId): LoanRepayment
    {
        /** @var JournalEntry $jurnalAsal */
        $jurnalAsal = JournalEntry::query()->withoutGlobalScopes()->findOrFail($lama->journal_entry_id);

        $pembalik = $this->journalEngine->reverse($jurnalAsal, "Pembatalan angsuran #{$lama->id}: {$alasan}", $userId);
        $lama->update([
            'cancelled_at' => now(),
            'cancelled_by' => $userId,
            'cancellation_reason' => mb_substr('Dibatalkan tanpa pengganti (Perbaikan Saldo Pinjaman): '.$alasan, 0, 255),
            'reversal_journal_entry_id' => $pembalik->id,
        ]);

        AuditLog::query()->create([
            'actor_id' => $userId,
            'actor_role' => auth()->user()?->getRoleNames()->implode(','),
            'branch_id' => $loan->branch_id,
            'action' => 'koreksi_angsuran',
            'auditable_type' => LoanRepayment::class,
            'auditable_id' => $lama->id,
            'before' => [
                'repayment_id' => $lama->id, 'tanggal' => $this->tanggal($lama), 'jumlah' => (float) $lama->amount,
                'pokok' => (float) $lama->principal_portion, 'jasa' => (float) $lama->interest_portion,
                'denda' => (float) $lama->penalty_portion, 'jurnal' => $jurnalAsal->id,
            ],
            'after' => [
                'dibatalkan_tanpa_pengganti' => true, 'jurnal_pembalik' => $pembalik->id, 'alasan' => $alasan,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        $this->selaraskanBilaPerlu($loan, $userId);

        return $lama->fresh();
    }

    /**
     * Selaraskan jadwal & status satu pinjaman dengan buku besar (tanpa
     * mengubah transaksi). Null bila sudah selaras.
     */
    public function selaraskanBilaPerlu(Loan $loan, int $userId): ?LoanScheduleAlignment
    {
        $loan = Loan::query()->withoutGlobalScopes()->findOrFail($loan->id);
        $rencana = $this->penyelarasan->rencanaUntuk($loan);

        if ($rencana === null || ! $rencana['perlu'] || ! $rencana['bisa']) {
            return null;
        }

        return $this->penyelarasan->jalankan([$loan->id], $userId);
    }

    /**
     * Akun kas penerima pada jurnal angsuran asal: baris debit yang bukan
     * akun piutang/jasa/denda produk pinjaman itu.
     */
    private function akunKasAsal(JournalEntry $jurnal, $product): ?int
    {
        $bukanKas = array_filter([
            $product->coa_receivable_account_id,
            $product->coa_interest_income_account_id,
            $product->coa_penalty_receivable_account_id,
        ]);

        $baris = $jurnal->lines->first(fn ($l) => (float) $l->debit > 0 && ! in_array((int) $l->chart_of_account_id, array_map('intval', $bukanKas), true));

        return $baris !== null && ChartOfAccount::query()->whereKey($baris->chart_of_account_id)->exists()
            ? (int) $baris->chart_of_account_id
            : null;
    }

    /**
     * Pinjaman migrasi: pembayaran s/d tanggal cutoff sudah termasuk saldo
     * awal, jadi angsuran di aplikasi harus SESUDAH cutoff. Pinjaman aplikasi:
     * tidak boleh sebelum tanggal pencairan.
     */
    private function terlaluDini(string $tanggal, ?Carbon $awal, bool $migrasi): bool
    {
        if ($awal === null) {
            return false;
        }

        return $migrasi ? $tanggal <= $awal->toDateString() : $tanggal < $awal->toDateString();
    }

    /** Tanggal transaksi angsuran (Y-m-d). */
    private function tanggal(LoanRepayment $r): string
    {
        return ($r->transaction_date ?? $r->paid_at ?? $r->created_at)->toDateString();
    }

    /** Tanggal mulai kartu: cutoff saldo awal (migrasi) atau tanggal pencairan. */
    private function tanggalAwal(Loan $loan, bool $migrasi): ?Carbon
    {
        if ($migrasi) {
            return $this->cutoffSaldoAwal();
        }

        return $loan->disbursed_at ? Carbon::parse($loan->disbursed_at) : null;
    }

    private ?Carbon $cutoff = null;

    private bool $cutoffDibaca = false;

    private function cutoffSaldoAwal(): ?Carbon
    {
        if (! $this->cutoffDibaca) {
            $tgl = OpeningBalanceBatch::query()->where('status', 'locked')->max('cutoff_date');
            $this->cutoff = $tgl ? Carbon::parse($tgl) : null;
            $this->cutoffDibaca = true;
        }

        return $this->cutoff;
    }
}
