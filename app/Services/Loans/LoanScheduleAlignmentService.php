<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\LoanScheduleAlignment;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penyelarasan Jadwal Angsuran — menyamakan sisa tagihan di loan_schedules
 * dengan sisa menurut BUKU BESAR.
 *
 * Buku besar adalah satu-satunya sumber kebenaran pembayaran: pokok awal
 * (saldo awal migrasi, atau plafon untuk pinjaman yang dicairkan di
 * aplikasi) dikurangi porsi pokok tiap angsuran yang tidak dibatalkan; jasa
 * sama caranya (lihat SisaPinjamanCalculator). Jadwal hanyalah rencana
 * tagihan — tapi jadwal-lah yang dilihat staf di Catat Angsuran, anggota
 * di portal, dan yang menentukan pinjaman ditandai lunas. Pemindaian 28 Sep
 * 2026 menemukan 112 dari 161 pinjaman aktif yang jadwalnya menyimpang dari
 * buku besar (total Rp 53,8 juta): jadwal hasil migrasi / backfill dibangun
 * dari riwayat sistem lama yang tidak persis sama dengan saldo akhirnya,
 * dan pembayaran yang dibatalkan setelah backfill tidak selalu membuka
 * kembali jadwal dengan tepat.
 *
 * Yang dilakukan: baris jadwal (pokok/jasa per angsuran) TIDAK diubah;
 * hanya kolom terbayar (paid_*) dan status yang dihitung ulang — diisi dari
 * angsuran tertua — sehingga sisa jadwal = sisa buku besar. Status pinjaman
 * mengikuti: lunas bila sisa pokok dan jasa nol, dibuka kembali bila
 * pinjaman "lunas" ternyata masih bersisa menurut buku besar. Jurnal tidak
 * disentuh sama sekali, dan itu dibuktikan sendiri oleh jalankan() lewat
 * potret pembukuan sebelum–sesudah.
 *
 * Aturan jasa (keputusan pengurus 28 Sep 2026: jasa wajib dibayar, tidak
 * pernah dihapuskan): untuk pinjaman migrasi, sisa jasa mengikuti sisa jasa
 * saldo awal; kalau saldo awalnya tidak mencatat sisa jasa (kosong/nol),
 * jasa jadwal DIBIARKAN apa adanya dan pinjaman tidak ditandai lunas selama
 * jasa jadwal masih bersisa. Untuk pinjaman yang dicairkan di aplikasi,
 * jasa awal = total jasa di jadwal pencairannya.
 *
 * Bisa dijalankan ulang kapan saja (pinjaman yang sudah selaras tidak
 * muncul lagi), dan tiap penyelarasan bisa dibatalkan dari riwayat.
 */
class LoanScheduleAlignmentService
{
    private const TOLERANSI = 0.5;

    public function __construct(private readonly SisaPinjamanCalculator $sisaPinjaman) {}

    /**
     * Pinjaman aktif/lunas yang jadwalnya tidak selaras dengan buku besar,
     * beserta rencana penyelarasannya — urut dari selisih terbesar.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function temukan(): Collection
    {
        $loans = Loan::query()
            ->whereIn('status', ['dicairkan', 'lunas'])
            ->with(['member', 'loanProduct', 'schedules'])
            ->get();

        return $this->rencanaBanyak($loans)
            ->filter(fn (array $r) => $r['perlu'])
            ->sortByDesc(fn (array $r) => abs($r['selisih_pokok']) + abs($r['selisih_jasa']))
            ->values();
    }

    /**
     * @param  Collection<int, Loan>  $loans  relasi `schedules` sudah dimuat
     * @return Collection<int, array<string, mixed>>
     */
    public function rencanaBanyak(Collection $loans): Collection
    {
        if ($loans->isEmpty()) {
            return collect();
        }

        $posisi = $this->sisaPinjaman->posisiAwal($loans);

        $bayar = LoanRepayment::query()
            ->withoutGlobalScope(BranchScope::class)
            ->whereIn('loan_id', $loans->pluck('id')->all())
            ->whereNull('cancelled_at')
            ->whereNull('migrated_at')
            ->selectRaw('loan_id, COALESCE(SUM(principal_portion),0) AS pokok, COALESCE(SUM(interest_portion),0) AS jasa')
            ->groupBy('loan_id')
            ->get()
            ->keyBy('loan_id');

        return $loans
            ->filter(fn (Loan $loan) => $loan->schedules->isNotEmpty())
            ->map(fn (Loan $loan) => $this->rencana(
                $loan,
                $posisi[$loan->id],
                (float) ($bayar[$loan->id]->pokok ?? 0),
                (float) ($bayar[$loan->id]->jasa ?? 0),
            ))
            ->values();
    }

    /**
     * Rencana untuk satu pinjaman — murni hitung, tidak menulis apa pun.
     *
     * @param  array{pokok: float, jasa: ?float, migrasi: bool}  $posisi
     * @return array<string, mixed>
     */
    private function rencana(Loan $loan, array $posisi, float $pokokBayar, float $jasaBayar): array
    {
        $jadwal = $loan->schedules->sortBy('installment_number')->values();

        $totalPokok = round((float) $jadwal->sum('principal_amount'), 2);
        $totalJasa = round((float) $jadwal->sum('interest_amount'), 2);
        $sisaPokokJadwal = round($totalPokok - (float) $jadwal->sum('paid_principal_amount'), 2);
        $sisaJasaJadwal = round($totalJasa - (float) $jadwal->sum('paid_interest_amount'), 2);

        $peringatan = [];
        $bisa = true;

        // --- pokok ---
        $pokokAwal = $posisi['pokok'];
        $sisaPokokBuku = round($pokokAwal - $pokokBayar, 2);
        if ($sisaPokokBuku < -self::TOLERANSI) {
            $peringatan[] = 'Porsi pokok yang dibayar (Rp '.number_format($pokokBayar, 0, ',', '.').') melebihi pokok awal (Rp '.number_format($pokokAwal, 0, ',', '.').') — sisa dianggap nol, tinjau riwayat angsurannya.';
        }
        $targetSisaPokok = max(0.0, $sisaPokokBuku);
        if ($targetSisaPokok > $totalPokok + self::TOLERANSI) {
            $bisa = false;
            $peringatan[] = 'Total pokok di jadwal (Rp '.number_format($totalPokok, 0, ',', '.').') lebih kecil dari sisa buku besar (Rp '.number_format($targetSisaPokok, 0, ',', '.').') — jadwal perlu dibangun ulang, tidak bisa diselaraskan.';
        }

        // --- jasa ---
        $jasaAwal = $posisi['migrasi'] ? $posisi['jasa'] : $totalJasa;
        $jasaDiselaraskan = $jasaAwal !== null && $jasaAwal > self::TOLERANSI;
        if ($jasaDiselaraskan) {
            $sisaJasaBuku = round($jasaAwal - $jasaBayar, 2);
            if ($sisaJasaBuku < -self::TOLERANSI) {
                $peringatan[] = 'Porsi jasa yang dibayar (Rp '.number_format($jasaBayar, 0, ',', '.').') melebihi jasa awal (Rp '.number_format($jasaAwal, 0, ',', '.').') — sisa jasa dianggap nol.';
            }
            $targetSisaJasa = max(0.0, $sisaJasaBuku);
            if ($targetSisaJasa > $totalJasa + self::TOLERANSI) {
                $bisa = false;
                $peringatan[] = 'Total jasa di jadwal (Rp '.number_format($totalJasa, 0, ',', '.').') lebih kecil dari sisa jasa buku besar (Rp '.number_format($targetSisaJasa, 0, ',', '.').') — jadwal perlu dibangun ulang.';
            }
        } else {
            $sisaJasaBuku = null;
            $targetSisaJasa = $sisaJasaJadwal;
            if ($posisi['migrasi']) {
                $peringatan[] = 'Saldo awal tidak mencatat sisa jasa — jasa jadwal dibiarkan apa adanya.';
            }
        }

        // --- sebar nilai terbayar ke baris jadwal, tertua dulu ---
        $sisaBayarPokok = round($totalPokok - min($targetSisaPokok, $totalPokok), 2);
        $sisaBayarJasa = round($totalJasa - min($targetSisaJasa, $totalJasa), 2);
        $baris = [];
        $rincian = [];

        foreach ($jadwal as $s) {
            $paidP = round(min((float) $s->principal_amount, $sisaBayarPokok), 2);
            $paidI = round(min((float) $s->interest_amount, $sisaBayarJasa), 2);
            $sisaBayarPokok = round($sisaBayarPokok - $paidP, 2);
            $sisaBayarJasa = round($sisaBayarJasa - $paidI, 2);
            $paid = round($paidP + $paidI, 2);
            $status = match (true) {
                $paid >= (float) $s->total_amount - 0.005 => 'lunas',
                $paid > 0 => 'sebagian',
                default => 'belum_bayar',
            };

            $lama = [
                'paid_principal_amount' => round((float) $s->paid_principal_amount, 2),
                'paid_interest_amount' => round((float) $s->paid_interest_amount, 2),
                'paid_amount' => round((float) $s->paid_amount, 2),
                'status' => $s->status,
            ];
            $baru = [
                'paid_principal_amount' => $paidP,
                'paid_interest_amount' => $paidI,
                'paid_amount' => $paid,
                'status' => $status,
            ];

            $berubah = abs($paidP - $lama['paid_principal_amount']) > 0.005
                || abs($paidI - $lama['paid_interest_amount']) > 0.005
                || abs($paid - $lama['paid_amount']) > 0.005
                || $status !== $lama['status'];

            if ($berubah) {
                $baris[$s->id] = $baru;
            }

            // Rincian sebelum → sesudah untuk SEMUA baris, supaya pengurus bisa
            // melihat jadwal utuh, bukan hanya baris yang berubah.
            $rincian[] = [
                'schedule_id' => $s->id,
                'no' => (int) $s->installment_number,
                'jatuh_tempo' => $s->due_date,
                'pokok' => round((float) $s->principal_amount, 2),
                'jasa' => round((float) $s->interest_amount, 2),
                'lama' => $lama,
                'baru' => $baru,
                'berubah' => $berubah,
            ];
        }

        $statusBaru = ($targetSisaPokok <= self::TOLERANSI && $targetSisaJasa <= self::TOLERANSI) ? 'lunas' : 'dicairkan';
        if ($loan->status === 'lunas' && $statusBaru === 'dicairkan') {
            $peringatan[] = 'Pinjaman berstatus LUNAS akan DIBUKA KEMBALI — menurut buku besar masih bersisa.';
        } elseif ($loan->status === 'dicairkan' && $statusBaru === 'lunas') {
            $peringatan[] = 'Pinjaman akan ditandai LUNAS.';
        }

        $selisihPokok = round($targetSisaPokok - $sisaPokokJadwal, 2);
        $selisihJasa = round($targetSisaJasa - $sisaJasaJadwal, 2);
        $perlu = abs($selisihPokok) > self::TOLERANSI
            || abs($selisihJasa) > self::TOLERANSI
            || $statusBaru !== $loan->status;

        return [
            'loan' => $loan,
            'loan_id' => $loan->id,
            'migrasi' => $posisi['migrasi'],
            'pokok_awal' => $pokokAwal,
            'jasa_awal' => $jasaAwal,
            'pokok_bayar' => round($pokokBayar, 2),
            'jasa_bayar' => round($jasaBayar, 2),
            'sisa_pokok_buku' => $sisaPokokBuku,
            'sisa_jasa_buku' => $sisaJasaBuku,
            'sisa_pokok_jadwal' => $sisaPokokJadwal,
            'sisa_jasa_jadwal' => $sisaJasaJadwal,
            'target_sisa_pokok' => $targetSisaPokok,
            'target_sisa_jasa' => $targetSisaJasa,
            'selisih_pokok' => $selisihPokok,
            'selisih_jasa' => $selisihJasa,
            'perlu' => $perlu,
            'bisa' => $bisa,
            'baris' => $baris,
            'baris_berubah' => count($baris),
            'rincian' => $rincian,
            'status_lama' => $loan->status,
            'status_baru' => $statusBaru,
            'peringatan' => $peringatan,
        ];
    }

    /**
     * Rencana lengkap (termasuk rincian tiap baris jadwal sebelum → sesudah)
     * untuk satu pinjaman — dipakai layar rincian sebelum penyelarasan.
     *
     * @return array<string, mixed>|null null bila pinjaman tidak punya jadwal
     */
    public function rencanaUntuk(Loan $loan): ?array
    {
        $loan->load(['member', 'schedules']);

        return $this->rencanaBanyak(collect([$loan]))->first();
    }

    /**
     * Rincian sebelum → sesudah dari penyelarasan yang SUDAH dijalankan,
     * dibaca dari payload (bukan dihitung ulang, supaya yang ditampilkan
     * persis yang waktu itu diubah). Baris jadwal yang tidak berubah tidak
     * disimpan di payload, jadi hanya baris yang diubah yang tampil.
     *
     * @return Collection<int, array<string, mixed>> satu unsur per pinjaman
     */
    public function rincianRiwayat(LoanScheduleAlignment $alignment): Collection
    {
        $payload = $alignment->payload ?? [];
        $loanIds = array_map('intval', array_keys($payload));

        $loans = Loan::query()->withoutGlobalScopes()
            ->whereIn('id', $loanIds)
            ->with('member')
            ->get()
            ->keyBy('id');

        $scheduleIds = [];
        foreach ($payload as $p) {
            $scheduleIds = array_merge($scheduleIds, array_map('intval', array_keys($p['baris'] ?? [])));
        }
        $jadwal = LoanSchedule::query()->whereIn('id', $scheduleIds)->get()->keyBy('id');

        return collect($payload)->map(function (array $p, $loanId) use ($loans, $jadwal, $alignment) {
            $loan = $loans[(int) $loanId] ?? null;
            $rincian = [];

            foreach ($p['baris'] ?? [] as $scheduleId => $lama) {
                /** @var LoanSchedule|null $s */
                $s = $jadwal[(int) $scheduleId] ?? null;
                if ($s === null) {
                    continue;
                }

                $lama = [
                    'paid_principal_amount' => round((float) $lama['paid_principal_amount'], 2),
                    'paid_interest_amount' => round((float) $lama['paid_interest_amount'], 2),
                    'paid_amount' => round((float) $lama['paid_amount'], 2),
                    'status' => $lama['status'],
                ];

                // Payload lama (sebelum kolom baris_baru ada) tidak menyimpan
                // nilai sesudah: selama belum dibatalkan, nilai di DB sekarang
                // adalah nilai sesudahnya.
                $baru = $p['baris_baru'][$scheduleId] ?? null;
                if ($baru === null && ! $alignment->isReverted()) {
                    $baru = [
                        'paid_principal_amount' => (float) $s->paid_principal_amount,
                        'paid_interest_amount' => (float) $s->paid_interest_amount,
                        'paid_amount' => (float) $s->paid_amount,
                        'status' => $s->status,
                    ];
                }
                if ($baru !== null) {
                    $baru = [
                        'paid_principal_amount' => round((float) $baru['paid_principal_amount'], 2),
                        'paid_interest_amount' => round((float) $baru['paid_interest_amount'], 2),
                        'paid_amount' => round((float) $baru['paid_amount'], 2),
                        'status' => $baru['status'],
                    ];
                }

                $rincian[] = [
                    'schedule_id' => $s->id,
                    'no' => (int) $s->installment_number,
                    'jatuh_tempo' => $s->due_date,
                    'pokok' => round((float) $s->principal_amount, 2),
                    'jasa' => round((float) $s->interest_amount, 2),
                    'lama' => $lama,
                    'baru' => $baru,
                    'berubah' => true,
                ];
            }

            usort($rincian, fn (array $a, array $b) => $a['no'] <=> $b['no']);

            return [
                'loan_id' => (int) $loanId,
                'loan' => $loan,
                'loan_number' => $p['loan_number'] ?? ($loan?->loan_number ?? '-'),
                'anggota' => $loan?->member?->name ?? '-',
                'status_lama' => $p['status_lama'] ?? null,
                'status_baru' => $p['status_baru'] ?? null,
                'selisih_pokok' => (float) ($p['selisih_pokok'] ?? 0),
                'selisih_jasa' => (float) ($p['selisih_jasa'] ?? 0),
                'sisa_pokok_jadwal_lama' => isset($p['sisa_pokok_jadwal_lama']) ? (float) $p['sisa_pokok_jadwal_lama'] : null,
                'sisa_pokok_jadwal_baru' => isset($p['sisa_pokok_jadwal_baru']) ? (float) $p['sisa_pokok_jadwal_baru'] : null,
                'sisa_jasa_jadwal_lama' => isset($p['sisa_jasa_jadwal_lama']) ? (float) $p['sisa_jasa_jadwal_lama'] : null,
                'sisa_jasa_jadwal_baru' => isset($p['sisa_jasa_jadwal_baru']) ? (float) $p['sisa_jasa_jadwal_baru'] : null,
                'rincian' => $rincian,
                'baris_berubah' => count($rincian),
            ];
        })->values();
    }

    /**
     * Selaraskan pinjaman-pinjaman terpilih dalam satu transaksi, lalu
     * buktikan jurnal tidak bergeser. Pinjaman yang ternyata sudah selaras
     * atau tidak bisa diselaraskan dilewati (diverifikasi ulang dari DB,
     * bukan percaya input form).
     *
     * @param  array<int, int>  $loanIds
     */
    public function jalankan(array $loanIds, int $userId): LoanScheduleAlignment
    {
        $loans = Loan::query()
            ->whereIn('id', $loanIds)
            ->whereIn('status', ['dicairkan', 'lunas'])
            ->with('schedules')
            ->get();

        $rencana = $this->rencanaBanyak($loans)->filter(fn (array $r) => $r['perlu'] && $r['bisa']);

        if ($rencana->isEmpty()) {
            throw new RuntimeException('Tidak ada pinjaman terpilih yang perlu (dan bisa) diselaraskan.');
        }

        $sebelum = $this->potretPembukuan();

        return DB::transaction(function () use ($rencana, $userId, $sebelum) {
            $payload = [];
            $jumlahBaris = 0;

            foreach ($rencana as $r) {
                /** @var Loan $loan */
                $loan = $r['loan'];
                $lama = [];

                foreach ($loan->schedules->whereIn('id', array_keys($r['baris'])) as $s) {
                    $lama[$s->id] = [
                        'paid_principal_amount' => (string) $s->paid_principal_amount,
                        'paid_interest_amount' => (string) $s->paid_interest_amount,
                        'paid_amount' => (string) $s->paid_amount,
                        'status' => $s->status,
                    ];
                }

                foreach ($r['baris'] as $scheduleId => $nilai) {
                    LoanSchedule::query()->where('id', $scheduleId)->where('loan_id', $loan->id)->update($nilai);
                }

                if ($r['status_baru'] !== $loan->status) {
                    $loan->update(['status' => $r['status_baru']]);
                }

                $payload[$loan->id] = [
                    'loan_number' => $loan->loan_number,
                    'status_lama' => $r['status_lama'],
                    'status_baru' => $r['status_baru'],
                    'selisih_pokok' => $r['selisih_pokok'],
                    'selisih_jasa' => $r['selisih_jasa'],
                    'sisa_pokok_jadwal_lama' => $r['sisa_pokok_jadwal'],
                    'sisa_pokok_jadwal_baru' => $r['target_sisa_pokok'],
                    'sisa_jasa_jadwal_lama' => $r['sisa_jasa_jadwal'],
                    'sisa_jasa_jadwal_baru' => $r['target_sisa_jasa'],
                    'baris' => $lama,
                    'baris_baru' => $r['baris'],
                ];
                $jumlahBaris += count($r['baris']);
            }

            $this->pastikanPembukuanUtuh($sebelum);

            return LoanScheduleAlignment::query()->create([
                'performed_by' => $userId,
                'loans_aligned' => count($payload),
                'rows_changed' => $jumlahBaris,
                'payload' => $payload,
            ]);
        });
    }

    /**
     * Kembalikan setiap baris jadwal dan status pinjaman PERSIS ke nilai
     * lamanya yang tersimpan di payload — bukan menghitung ulang.
     */
    public function batalkan(LoanScheduleAlignment $alignment, int $userId): LoanScheduleAlignment
    {
        if ($alignment->isReverted()) {
            throw new RuntimeException('Penyelarasan ini sudah dibatalkan sebelumnya.');
        }

        $sebelum = $this->potretPembukuan();

        return DB::transaction(function () use ($alignment, $userId, $sebelum) {
            foreach ($alignment->payload as $loanId => $p) {
                foreach ($p['baris'] as $scheduleId => $lama) {
                    LoanSchedule::query()->where('id', $scheduleId)->where('loan_id', $loanId)->update($lama);
                }

                if (($p['status_lama'] ?? null) !== ($p['status_baru'] ?? null)) {
                    Loan::query()->withoutGlobalScopes()->where('id', $loanId)->update(['status' => $p['status_lama']]);
                }
            }

            $this->pastikanPembukuanUtuh($sebelum);

            $alignment->update(['reverted_at' => now(), 'reverted_by' => $userId]);

            return $alignment->fresh();
        });
    }

    /** @return array<string, float|int> */
    private function potretPembukuan(): array
    {
        return [
            'entries' => DB::table('journal_entries')->count(),
            'lines' => DB::table('journal_lines')->count(),
            'debit' => (float) DB::table('journal_lines')->sum('debit'),
            'credit' => (float) DB::table('journal_lines')->sum('credit'),
            'repayments' => DB::table('loan_repayments')->count(),
        ];
    }

    /** @param  array<string, float|int>  $sebelum */
    private function pastikanPembukuanUtuh(array $sebelum): void
    {
        $sesudah = $this->potretPembukuan();

        $utuh = $sebelum['entries'] === $sesudah['entries']
            && $sebelum['lines'] === $sesudah['lines']
            && $sebelum['repayments'] === $sesudah['repayments']
            && abs($sebelum['debit'] - $sesudah['debit']) < 0.01
            && abs($sebelum['credit'] - $sesudah['credit']) < 0.01;

        if (! $utuh) {
            throw new RuntimeException(
                'Penyelarasan dibatalkan: pembukuan berubah, padahal yang disentuh hanya jadwal. Tidak ada data yang tersimpan.'
            );
        }
    }
}
