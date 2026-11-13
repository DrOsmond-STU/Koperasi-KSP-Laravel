<?php

namespace App\Services\Accounting;

use App\Exceptions\Accounting\AdjustmentException;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Jurnal Penyesuaian (PRD §2.7, Task 5.2). Every correction goes through
 * JournalEngine::reverse() — the original entry is NEVER edited/deleted
 * (LED-06) — and requires the acting user to re-confirm their password
 * immediately before posting (re-auth, PRD §5.2 "konfirmasi re-auth/OTP"),
 * on top of whatever permission gate already applies to the route.
 */
class AdjustmentService
{
    /**
     * Modul yang punya pembatalan sendiri (membalik jurnal SEKALIGUS menandai
     * transaksinya batal) => label menu untuk pesan ke pengguna.
     */
    private const MODUL_DENGAN_PEMBATALAN = [
        'savings_transactions' => 'Transaksi Simpanan (menu Transaksi Teller / Riwayat Simpanan)',
        'loan_repayments' => 'Angsuran Pinjaman (menu Daftar Angsuran Anggota)',
        'retribution_transactions' => 'Retribusi UPF (menu Retribusi UPF)',
        'fixed_asset_disposals' => 'Pelepasan Aktiva Tetap',
        'stock_adjustments' => 'Koreksi Persediaan',
    ];

    public function __construct(private readonly JournalEngine $journalEngine) {}

    /**
     * Nama modul pemilik jurnal ini bila jurnal (atau jurnal pembalik resmi)
     * sebuah transaksi yang punya tombol Batalkan sendiri; null bila boleh
     * dikoreksi lewat Jurnal Penyesuaian (jurnal umum, kas teller, jurnal
     * modul tanpa pembatalan, atau jurnal pembalik berlebih yang tidak
     * tercatat di transaksi mana pun).
     */
    public function modulPemilik(JournalEntry $entry): ?string
    {
        foreach (self::MODUL_DENGAN_PEMBATALAN as $tabel => $label) {
            $dipakai = DB::table($tabel)
                ->where(fn ($q) => $q->where('journal_entry_id', $entry->id)->orWhere('reversal_journal_entry_id', $entry->id))
                ->exists();

            if ($dipakai) {
                return $label;
            }
        }

        $pencairan = ($entry->source_type === (new Loan)->getMorphClass() && $entry->reversal_of_entry_id === null)
            || DB::table('loans')->where('reversal_journal_entry_id', $entry->id)->exists();

        return $pencairan ? 'Pencairan Pinjaman (menu Persetujuan Pinjaman)' : null;
    }

    public function reverse(JournalEntry $originalEntry, string $reason, User $user, string $password): JournalEntry
    {
        if (! Hash::check($password, $user->password)) {
            throw AdjustmentException::reAuthFailed();
        }

        if (($modul = $this->modulPemilik($originalEntry)) !== null) {
            throw AdjustmentException::milikModul($originalEntry->id, $modul);
        }

        return DB::transaction(function () use ($originalEntry, $reason, $user) {
            // Kunci baris entri asal supaya dua submit yang hampir bersamaan
            // (double-click, resubmit setelah koneksi lambat) tidak sama-sama
            // lolos pengecekan "sudah dibalik?" di bawah sebelum salah
            // satunya selesai membuat jurnal balik — modul lain (Simpanan,
            // Pinjaman, dst.) sudah punya pengaman via status transaksinya
            // sendiri sebelum memanggil reverse(); alur koreksi manual ini
            // beroperasi langsung di atas JournalEntry tanpa status seperti
            // itu, jadi pengecekannya harus di sini.
            $locked = JournalEntry::query()->whereKey($originalEntry->id)->lockForUpdate()->firstOrFail();

            if ($locked->reversals()->exists()) {
                throw AdjustmentException::alreadyReversed($locked->id);
            }

            return $this->journalEngine->reverse($originalEntry, $reason, $user->id);
        });
    }
}
