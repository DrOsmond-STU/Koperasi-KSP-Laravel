<?php

namespace App\Services\OpeningBalance;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceCoa;
use App\Models\OpeningBalanceCorrection;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Koreksi Saldo Awal Neraca (permintaan pemilik 13 Nov 2026).
 *
 * Saldo awal hasil migrasi (opening_balance_coa) dan jurnal pembukaannya
 * TIDAK PERNAH diubah atau dihapus. Pengguna mengisi saldo awal yang benar
 * per akun; sistem menghitung selisihnya terhadap saldo awal sekarang dan
 * memposting SATU jurnal koreksi baru bertanggal cut-off batch (source =
 * OpeningBalanceCorrection). Karena bertanggal cut-off, Buku Besar, Neraca,
 * Neraca Saldo dan Laba Rugi per tanggal mana pun sesudah cut-off langsung
 * memakai saldo awal yang sudah dikoreksi; laporan Saldo Awal Neraca
 * menggabungkannya lewat gabungKoreksi().
 *
 * Saldo dinyatakan menurut saldo normal akun: positif = di sisi normalnya
 * (debit untuk Aset/Beban, kredit untuk Kewajiban/Ekuitas/Pendapatan).
 *
 * Koreksi bisa dibatalkan — jurnalnya dibalik (tanggal sama), barisnya
 * ditandai dibatalkan; tidak ada yang dihapus.
 */
class OpeningBalanceNeracaCorrectionService
{
    public function __construct(private readonly JournalEngine $journalEngine) {}

    /** Akun SHU Tahun Berjalan dihitung dari akun Laba/Rugi — tidak bisa dikoreksi langsung. */
    public function kodeAkunShu(): string
    {
        return (string) config('koperasi.akun_shu_berjalan', '3150');
    }

    /**
     * Posisi saldo awal per akun: asal (migrasi), koreksi aktif, sekarang.
     *
     * @return array{baris: Collection<int, array{account: ChartOfAccount, asal: float, koreksi: float, sekarang: float, bisa_diubah: bool}>, ringkasan: array<string, float>}
     */
    public function posisi(OpeningBalanceBatch $batch): array
    {
        $asal = OpeningBalanceCoa::query()
            ->where('opening_balance_batch_id', $batch->id)
            ->selectRaw("chart_of_account_id, SUM(CASE WHEN position = 'debit' THEN amount ELSE 0 END) d, SUM(CASE WHEN position = 'kredit' THEN amount ELSE 0 END) k")
            ->groupBy('chart_of_account_id')
            ->get()
            ->keyBy('chart_of_account_id');
        $koreksi = $this->koreksiPerAkun([$batch->id]);

        $baris = ChartOfAccount::query()
            ->whereIn('statement', ['NERACA', 'LABA_RUGI'])
            ->where(fn ($q) => $q->where('is_postable', true)->orWhereIn('id', $asal->keys()))
            ->get()
            ->sortBy(fn (ChartOfAccount $a) => $a->code, SORT_STRING)
            ->values()
            ->map(function (ChartOfAccount $account) use ($asal, $koreksi) {
                $a = $asal->get($account->id);
                $nilaiAsal = $this->normal($account, (float) ($a->d ?? 0), (float) ($a->k ?? 0));
                $k = $koreksi[$account->id] ?? ['debit' => 0.0, 'kredit' => 0.0];
                $nilaiKoreksi = $this->normal($account, $k['debit'], $k['kredit']);

                return [
                    'account' => $account,
                    'asal' => $nilaiAsal,
                    'koreksi' => $nilaiKoreksi,
                    'sekarang' => round($nilaiAsal + $nilaiKoreksi, 2),
                    'bisa_diubah' => $account->is_postable && $account->code !== $this->kodeAkunShu(),
                ];
            });

        return ['baris' => $baris, 'ringkasan' => $this->ringkasan($baris, 'sekarang')];
    }

    /**
     * Susun jurnal koreksi dari saldo awal yang benar per akun.
     *
     * @param  array<int|string, mixed>  $target  chart_of_account_id => saldo awal yang benar (saldo normal)
     * @return array{baris: list<array>, total_debit: float, total_kredit: float, selisih: float, sebelum: array, sesudah: array, posisi: Collection}
     */
    public function rencana(OpeningBalanceBatch $batch, array $target, ?int $penyeimbangId = null): array
    {
        $posisi = $this->posisi($batch)['baris']->keyBy(fn ($b) => $b['account']->id);
        $baris = [];

        foreach ($target as $accountId => $nilai) {
            if ($nilai === null || $nilai === '') {
                continue;
            }
            $p = $posisi->get((int) $accountId);
            if ($p === null) {
                throw new RuntimeException("Akun #{$accountId} tidak ditemukan atau tidak bisa dipakai di saldo awal.");
            }
            if (! is_numeric($nilai)) {
                throw new RuntimeException("Nilai untuk akun {$p['account']->code} bukan angka.");
            }
            $delta = round((float) $nilai - $p['sekarang'], 2);
            if (abs($delta) < 0.005) {
                continue;
            }
            if (! $p['bisa_diubah']) {
                throw new RuntimeException("Akun {$p['account']->code} {$p['account']->name} tidak bisa dikoreksi langsung"
                    .($p['account']->code === $this->kodeAkunShu() ? ' (SHU Tahun Berjalan dihitung dari akun Laba/Rugi).' : ' (bukan akun postable).'));
            }
            $baris[] = $this->barisKoreksi($p['account'], $p['sekarang'], $delta, false);
        }

        $totalDebit = round(array_sum(array_column($baris, 'debit')), 2);
        $totalKredit = round(array_sum(array_column($baris, 'kredit')), 2);
        $selisih = round($totalDebit - $totalKredit, 2);

        if ($penyeimbangId !== null && abs($selisih) >= 0.005) {
            $p = $posisi->get($penyeimbangId);
            if ($p === null || ! $p['bisa_diubah']) {
                throw new RuntimeException('Akun penyeimbang tidak valid atau tidak bisa dikoreksi.');
            }
            if (collect($baris)->contains(fn ($b) => $b['account']->id === $penyeimbangId)) {
                throw new RuntimeException("Akun penyeimbang {$p['account']->code} juga diubah manual — pilih akun penyeimbang lain atau kosongkan isiannya.");
            }
            // Lebih banyak debit → penyeimbang dikredit, dan sebaliknya.
            $d = $selisih < 0 ? -$selisih : 0.0;
            $k = $selisih > 0 ? $selisih : 0.0;
            $delta = $this->normal($p['account'], $d, $k);
            $baris[] = $this->barisKoreksi($p['account'], $p['sekarang'], $delta, true);
            $totalDebit = round($totalDebit + $d, 2);
            $totalKredit = round($totalKredit + $k, 2);
            $selisih = 0.0;
        }

        $perubahan = collect($baris)->mapWithKeys(fn ($b) => [$b['account']->id => $b['delta']]);
        $sesudah = $posisi->values()->map(fn ($p) => $p + ['sesudah' => round($p['sekarang'] + ($perubahan[$p['account']->id] ?? 0), 2)]);

        return [
            'baris' => $baris,
            'total_debit' => $totalDebit,
            'total_kredit' => $totalKredit,
            'selisih' => $selisih,
            'sebelum' => $this->ringkasan($sesudah, 'sekarang'),
            'sesudah' => $this->ringkasan($sesudah, 'sesudah'),
        ];
    }

    /**
     * Simpan koreksi: satu baris opening_balance_corrections + satu jurnal
     * koreksi bertanggal cut-off. Data lama tidak disentuh.
     */
    public function simpan(OpeningBalanceBatch $batch, array $target, ?int $penyeimbangId, string $alasan, User $user, ?string $idempotencyKey = null): OpeningBalanceCorrection
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw new RuntimeException('Alasan koreksi wajib diisi.');
        }

        return DB::transaction(function () use ($batch, $target, $penyeimbangId, $alasan, $user, $idempotencyKey) {
            // Satu koreksi per batch dalam satu waktu — dua kiriman hampir
            // bersamaan dihitung dari saldo yang sama, jadi yang kedua harus
            // menunggu lalu dihitung ulang (atau dikenali sebagai klik ganda).
            OpeningBalanceBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $jurnalId = DB::table('journal_entry_idempotency_keys')->where('idempotency_key', $idempotencyKey)->value('journal_entry_id');
                if ($jurnalId !== null) {
                    $sudah = OpeningBalanceCorrection::query()->where('journal_entry_id', $jurnalId)->first();
                    if ($sudah !== null) {
                        return $sudah;
                    }
                }
            }

            $rencana = $this->rencana($batch, $target, $penyeimbangId);
            if ($rencana['baris'] === []) {
                throw new RuntimeException('Tidak ada saldo awal yang berubah.');
            }
            if (abs($rencana['selisih']) >= 0.005) {
                throw new RuntimeException('Koreksi belum seimbang: total debit Rp '.number_format($rencana['total_debit'], 2, ',', '.')
                    .' ≠ total kredit Rp '.number_format($rencana['total_kredit'], 2, ',', '.').'. Pilih akun penyeimbang atau sesuaikan angkanya.');
            }

            $koreksi = OpeningBalanceCorrection::query()->create([
                'opening_balance_batch_id' => $batch->id,
                'reason' => mb_substr($alasan, 0, 255),
                'created_by' => $user->id,
            ]);

            $jurnal = $this->journalEngine->post([
                'branch_id' => $batch->branch_id,
                'entry_date' => $batch->cutoff_date->toDateString(),
                'description' => mb_substr("Koreksi saldo awal neraca (batch #{$batch->id}, koreksi #{$koreksi->id}): {$alasan}", 0, 255),
                'created_by' => $user->id,
                'source' => $koreksi,
                'idempotency_key' => $idempotencyKey,
                'lines' => array_map(fn ($b) => [
                    'chart_of_account_id' => $b['account']->id,
                    'debit' => $b['debit'],
                    'credit' => $b['kredit'],
                ], $rencana['baris']),
            ]);

            $koreksi->update(['journal_entry_id' => $jurnal->id]);

            return $koreksi->fresh();
        });
    }

    /** Batalkan koreksi: jurnalnya dibalik (tanggal sama), barisnya ditandai. */
    public function batalkan(OpeningBalanceCorrection $koreksi, string $alasan, User $user): OpeningBalanceCorrection
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw new RuntimeException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($koreksi, $alasan, $user) {
            $k = OpeningBalanceCorrection::query()->whereKey($koreksi->id)->lockForUpdate()->firstOrFail();
            if ($k->isCancelled()) {
                throw new RuntimeException("Koreksi #{$k->id} sudah dibatalkan.");
            }

            $jurnal = JournalEntry::query()->with('lines')->findOrFail($k->journal_entry_id);
            $pembalik = $this->journalEngine->reverse($jurnal, "Pembatalan koreksi saldo awal #{$k->id}: {$alasan}", $user->id);

            $k->update([
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => mb_substr($alasan, 0, 255),
                'reversal_journal_entry_id' => $pembalik->id,
            ]);

            return $k->fresh();
        });
    }

    /**
     * Jumlah debit/kredit koreksi yang masih berlaku per akun. Koreksi yang
     * dibatalkan, atau yang jurnalnya dibalik lewat Jurnal Penyesuaian,
     * tidak dihitung.
     *
     * @param  iterable<int>  $batchIds
     * @return array<int, array{debit: float, kredit: float}>
     */
    public function koreksiPerAkun(iterable $batchIds): array
    {
        $ids = collect($batchIds)->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return [];
        }

        return JournalLine::query()
            ->join('journal_entries as je', 'je.id', '=', 'journal_lines.journal_entry_id')
            ->join('opening_balance_corrections as obc', 'obc.journal_entry_id', '=', 'je.id')
            ->whereIn('obc.opening_balance_batch_id', $ids)
            ->whereNull('obc.cancelled_at')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('journal_entries as r')->whereColumn('r.reversal_of_entry_id', 'je.id'))
            ->groupBy('journal_lines.chart_of_account_id')
            ->selectRaw('journal_lines.chart_of_account_id as akun, SUM(journal_lines.debit) as d, SUM(journal_lines.credit) as k')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->akun => ['debit' => (float) $r->d, 'kredit' => (float) $r->k]])
            ->all();
    }

    /**
     * Untuk laporan Saldo Awal: baris opening_balance_coa digabung dengan
     * koreksi yang berlaku. Akun yang tidak dikoreksi dikembalikan apa
     * adanya; akun yang dikoreksi diganti satu baris (tidak disimpan) berisi
     * saldo awal sesudah koreksi. Tidak ada yang ditulis ke database.
     *
     * @param  Collection<int, OpeningBalanceCoa>  $lines  sudah memuat relasi account
     * @param  iterable<int>  $batchIds
     * @return Collection<int, OpeningBalanceCoa>
     */
    public function gabungKoreksi(Collection $lines, iterable $batchIds): Collection
    {
        $koreksi = $this->koreksiPerAkun($batchIds);
        if ($koreksi === []) {
            return $lines;
        }

        $tetap = $lines->reject(fn (OpeningBalanceCoa $l) => isset($koreksi[$l->chart_of_account_id]));
        $akun = ChartOfAccount::query()->whereIn('id', array_keys($koreksi))->get()->keyBy('id');

        foreach ($koreksi as $accountId => $k) {
            $asal = $lines->where('chart_of_account_id', $accountId);
            $netoDebit = round((float) $asal->where('position', 'debit')->sum('amount') - (float) $asal->where('position', 'kredit')->sum('amount')
                + $k['debit'] - $k['kredit'], 2);
            if (abs($netoDebit) < 0.005 || ! $akun->has($accountId)) {
                continue;
            }
            $baris = new OpeningBalanceCoa([
                'chart_of_account_id' => $accountId,
                'position' => $netoDebit > 0 ? 'debit' : 'kredit',
                'amount' => abs($netoDebit),
            ]);
            $baris->setRelation('account', $akun[$accountId]);
            $tetap->push($baris);
        }

        return $tetap->values();
    }

    /** @return Collection<int, OpeningBalanceCorrection> */
    public function riwayat(OpeningBalanceBatch $batch): Collection
    {
        return OpeningBalanceCorrection::query()
            ->where('opening_balance_batch_id', $batch->id)
            ->with(['journalEntry.lines.account', 'createdBy', 'cancelledBy'])
            ->latest('id')
            ->get();
    }

    private function barisKoreksi(ChartOfAccount $account, float $sekarang, float $delta, bool $penyeimbang): array
    {
        $tambahDiDebit = ($account->normal_balance === 'DEBIT') === ($delta > 0);

        return [
            'account' => $account,
            'sekarang' => $sekarang,
            'menjadi' => round($sekarang + $delta, 2),
            'delta' => $delta,
            'debit' => $tambahDiDebit ? abs($delta) : 0.0,
            'kredit' => $tambahDiDebit ? 0.0 : abs($delta),
            'penyeimbang' => $penyeimbang,
        ];
    }

    private function normal(ChartOfAccount $account, float $debit, float $kredit): float
    {
        return round($account->normal_balance === 'DEBIT' ? $debit - $kredit : $kredit - $debit, 2);
    }

    /**
     * Ringkasan neraca saldo awal: Aset vs Kewajiban + Ekuitas + SHU
     * (akun Laba/Rugi). Selisih ≠ 0 berarti neraca saldo awal timpang.
     */
    private function ringkasan(Collection $baris, string $kolom): array
    {
        $jumlah = fn (string $tipe) => round($baris->filter(fn ($b) => $b['account']->statement === 'NERACA' && $b['account']->type === $tipe)->sum($kolom), 2);
        $shu = round($baris->filter(fn ($b) => $b['account']->statement === 'LABA_RUGI')
            ->sum(fn ($b) => $b['account']->normal_balance === 'KREDIT' ? $b[$kolom] : -$b[$kolom]), 2);
        $aset = $jumlah('ASET');
        $kewajiban = $jumlah('LIABILITAS');
        $ekuitas = $jumlah('EKUITAS');

        return [
            'aset' => $aset,
            'kewajiban' => $kewajiban,
            'ekuitas' => $ekuitas,
            'shu' => $shu,
            'pasiva' => round($kewajiban + $ekuitas + $shu, 2),
            'selisih' => round($aset - $kewajiban - $ekuitas - $shu, 2),
        ];
    }
}
