<?php

namespace App\Services\Savings;

use App\Exceptions\Savings\InsufficientBalanceException;
use App\Exceptions\Savings\TransactionAlreadyCancelledException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * Setor/tarik simpanan lewat Teller (PRD §8, Task 1.12). The only writer of
 * `savings_accounts.balance` and `savings_transactions` — every call posts
 * through JournalEngine in the same DB transaction so the cached `balance`
 * and the ledger can never drift apart.
 */
class SavingsService
{
    /**
     * Kode cabang "Unit Koperasi Simpan Pinjam (KSP)" — dipakai sebagai
     * akun kas transaksi Teller (setor/tarik/buka rekening).
     *
     * Sebelumnya di-hardcode ke akun kas konsolidasi 1101 untuk SEMUA
     * cabang — laporan staf 24 Agu 2026: "akun lawan kas nya salah...
     * ini harusnya masuk ke cabang KSP". Sempat dicoba dibuat resolusi
     * PER CABANG rekening (mirror LoanRepaymentService), tapi ternyata
     * SEMUA 1.226 rekening simpanan aktif di production ber-branch_id ke
     * cabang ROOT "KPPD Pusat" (bukan ke Unit KSP/USP/UPF sama sekali) —
     * jadi resolusi per-cabang rekening selalu jatuh ke akun kas KPPD
     * Pusat (1101100 "KAS KECIL (KSP)"), bukan akun kas Unit KSP yang
     * dimaksud (laporan susulan: "kenapa saat jurnal jadi 1101100 — KAS
     * KECIL (KSP)" padahal banner default sudah benar 1101500). Jadi di
     * sini SELALU dipakai akun kas cabang KSP ini, terlepas dari
     * branch_id rekeningnya sendiri.
     *
     * Yang di-hardcode di sini adalah kode CABANG-nya, bukan kode akun —
     * akun kas cabang KSP sendiri tetap bisa diedit kapan saja lewat
     * admin/pengaturan/kas-cabang (branches.cash_account_id), jadi
     * berubah otomatis tanpa deploy kode baru kalau nanti diganti.
     */
    private const DEFAULT_BRANCH_CODE = '001';

    /** Kode akun kas konsolidasi — cuma dipakai sebagai jaring pengaman
     *  kalau BAHKAN cabang KSP di atas belum/tidak punya akun kas
     *  terkonfigurasi (seharusnya tidak pernah kejadian, tapi mencegah
     *  error 500 alih-alih diam-diam salah posting). */
    private const FALLBACK_CASH_ACCOUNT_CODE = '1101';

    public function __construct(private readonly JournalEngine $journalEngine) {}

    public function openAccount(
        Member $member,
        SavingsProduct $product,
        int $branchId,
        float $initialDeposit,
        int $createdBy,
    ): SavingsAccount {
        return DB::transaction(function () use ($member, $product, $branchId, $initialDeposit, $createdBy) {
            $account = SavingsAccount::query()->create([
                'branch_id' => $branchId,
                'member_id' => $member->id,
                'savings_product_id' => $product->id,
                'account_number' => $this->generateAccountNumber($product),
                'balance' => 0,
                'status' => 'aktif',
                'opened_at' => now()->toDateString(),
            ]);

            if ($initialDeposit > 0) {
                $this->deposit($account, $initialDeposit, $createdBy, 'Setoran awal pembukaan rekening');
            }

            return $account->fresh();
        });
    }

    /**
     * $date = tanggal transaksi SEBENARNYA (mis. staf menyusulkan setoran
     * lama yang belum sempat dicatat) — default ke hari ini kalau tidak
     * diisi. Dipakai untuk savings_transactions.transaction_date DAN
     * entry_date jurnal, supaya keduanya selalu konsisten (mirror pola
     * LoanRepaymentService::recordManualPayment() / RetributionService::
     * record()).
     */
    public function deposit(
        SavingsAccount $account,
        float $amount,
        int $createdBy,
        ?string $description = null,
        ?string $idempotencyKey = null,
        ?\DateTimeInterface $date = null,
    ): SavingsTransaction {
        $entryDate = $date ?? now();

        return DB::transaction(function () use ($account, $amount, $createdBy, $description, $idempotencyKey, $entryDate) {
            $product = $account->savingsProduct;
            $newBalance = bcadd((string) $account->balance, (string) $amount, 2);

            $entry = $this->journalEngine->post([
                'branch_id' => $account->branch_id,
                'entry_date' => $entryDate->format('Y-m-d'),
                'description' => $description ?? "Setoran simpanan {$account->account_number}",
                'created_by' => $createdBy,
                'source' => $account,
                'idempotency_key' => $idempotencyKey,
                'lines' => [
                    ['chart_of_account_id' => $this->cashAccount()->id, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $product->coa_liability_account_id, 'debit' => 0, 'credit' => $amount],
                ],
            ]);

            $account->update(['balance' => $newBalance]);

            return SavingsTransaction::query()->create([
                'branch_id' => $account->branch_id,
                'savings_account_id' => $account->id,
                'type' => 'setor',
                'transaction_date' => $entryDate->format('Y-m-d'),
                'amount' => $amount,
                'balance_after' => $newBalance,
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
                'description' => $description,
            ]);
        });
    }

    public function withdraw(
        SavingsAccount $account,
        float $amount,
        int $createdBy,
        ?string $description = null,
        ?string $idempotencyKey = null,
        ?\DateTimeInterface $date = null,
    ): SavingsTransaction {
        if (bccomp((string) $account->balance, (string) $amount, 2) < 0) {
            throw new InsufficientBalanceException($account->account_number, (string) $account->balance, (string) $amount);
        }

        $entryDate = $date ?? now();

        return DB::transaction(function () use ($account, $amount, $createdBy, $description, $idempotencyKey, $entryDate) {
            $product = $account->savingsProduct;
            $newBalance = bcsub((string) $account->balance, (string) $amount, 2);

            $entry = $this->journalEngine->post([
                'branch_id' => $account->branch_id,
                'entry_date' => $entryDate->format('Y-m-d'),
                'description' => $description ?? "Penarikan simpanan {$account->account_number}",
                'created_by' => $createdBy,
                'source' => $account,
                'idempotency_key' => $idempotencyKey,
                'lines' => [
                    ['chart_of_account_id' => $product->coa_liability_account_id, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $this->cashAccount()->id, 'debit' => 0, 'credit' => $amount],
                ],
            ]);

            $account->update(['balance' => $newBalance]);

            return SavingsTransaction::query()->create([
                'branch_id' => $account->branch_id,
                'savings_account_id' => $account->id,
                'type' => 'tarik',
                'transaction_date' => $entryDate->format('Y-m-d'),
                'amount' => $amount,
                'balance_after' => $newBalance,
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
                'description' => $description,
            ]);
        });
    }

    /**
     * Reduces the cached balance for a non-cash debit journaled by the
     * CALLER (e.g. POS "potong saldo simpanan" — the money never leaves as
     * cash, it becomes sales revenue in the same combined journal entry,
     * so withdraw()'s own Dr Kewajiban/Cr Kas journal would be wrong here).
     * Still the only place `balance`/`savings_transactions` are written,
     * keeping the invariant on SavingsAccount intact — the caller must
     * post its own journal FIRST, in the same DB transaction, and pass its id.
     */
    public function debitBalanceForNonCashTransaction(
        SavingsAccount $account,
        string $amount,
        int $journalEntryId,
        int $createdBy,
        ?string $description = null,
    ): SavingsTransaction {
        if (bccomp((string) $account->balance, $amount, 2) < 0) {
            throw new InsufficientBalanceException($account->account_number, (string) $account->balance, $amount);
        }

        $newBalance = bcsub((string) $account->balance, $amount, 2);
        $account->update(['balance' => $newBalance]);

        return SavingsTransaction::query()->create([
            'branch_id' => $account->branch_id,
            'savings_account_id' => $account->id,
            'type' => 'tarik',
            'amount' => $amount,
            'balance_after' => $newBalance,
            'journal_entry_id' => $journalEntryId,
            'created_by' => $createdBy,
            'description' => $description,
        ]);
    }

    /**
     * Mirror of debitBalanceForNonCashTransaction() — restores balance for
     * a Retur Penjualan against a "potong saldo simpanan" sale. Crediting
     * never fails a sufficiency check.
     */
    public function creditBalanceForNonCashTransaction(
        SavingsAccount $account,
        string $amount,
        int $journalEntryId,
        int $createdBy,
        ?string $description = null,
    ): SavingsTransaction {
        $newBalance = bcadd((string) $account->balance, $amount, 2);
        $account->update(['balance' => $newBalance]);

        return SavingsTransaction::query()->create([
            'branch_id' => $account->branch_id,
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => $amount,
            'balance_after' => $newBalance,
            'journal_entry_id' => $journalEntryId,
            'created_by' => $createdBy,
            'description' => $description,
        ]);
    }

    /**
     * Pembatalan (void) transaksi yang sudah diposting — baris asli TIDAK
     * pernah dihapus/diubah nilainya, hanya ditandai dibatalkan. Membalik
     * jurnal via JournalEngine::reverse() (mengembalikan reversal_of_entry_id
     * yang tertaut) dan mengembalikan efek saldo (setor dibalik jadi
     * pengurangan, tarik dibalik jadi penambahan) — arah kebalikan dari
     * `type` transaksi aslinya, bukan mengulang deposit()/withdraw() (yang
     * masing-masing akan memposting jurnal BARU, bukan membalik jurnal
     * asli).
     */
    public function reverseTransaction(SavingsTransaction $transaction, string $reason, int $cancelledBy): SavingsTransaction
    {
        if ($transaction->isCancelled()) {
            throw new TransactionAlreadyCancelledException;
        }

        return DB::transaction(function () use ($transaction, $reason, $cancelledBy) {
            $account = $transaction->savingsAccount;
            $amount = (string) $transaction->amount;

            if ($transaction->type === 'setor') {
                if (bccomp((string) $account->balance, $amount, 2) < 0) {
                    throw new InsufficientBalanceException($account->account_number, (string) $account->balance, $amount);
                }
                $newBalance = bcsub((string) $account->balance, $amount, 2);
            } else {
                $newBalance = bcadd((string) $account->balance, $amount, 2);
            }

            $reversalEntry = $this->journalEngine->reverse($transaction->journalEntry, $reason, $cancelledBy);

            $account->update(['balance' => $newBalance]);

            $transaction->update([
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
                'cancellation_reason' => $reason,
                'reversal_journal_entry_id' => $reversalEntry->id,
            ]);

            return $transaction->fresh();
        });
    }

    /**
     * Edit — laporan staf 26 Agu 2026: staf perlu bisa mengoreksi transaksi
     * Setor/Tarik yang salah catat (tanggal/jenis/nominal/keterangan)
     * langsung dari halaman Riwayat. Ledger append-only (JournalEngine,
     * LED-06) — jadi ini BUKAN update-in-place, melainkan reverseTransaction()
     * (membalik jurnal + saldo, menandai baris asli dibatalkan) diikuti
     * deposit()/withdraw() BARU dengan nilai yang sudah dikoreksi, keduanya
     * dalam SATU transaksi DB supaya atomik (baris asli TIDAK pernah
     * ditandai dibatalkan kalau langkah kedua gagal, mis. saldo tidak
     * cukup untuk Tarik yang baru). Rekening TIDAK bisa diganti lewat sini
     * — kalau salah rekening, batalkan lewat reverseTransaction() lalu
     * catat manual dari form biasa.
     */
    public function editTransaction(
        SavingsTransaction $transaction,
        string $newType,
        float $newAmount,
        ?string $newDescription,
        \DateTimeInterface $newDate,
        string $reason,
        int $editedBy,
    ): SavingsTransaction {
        return DB::transaction(function () use ($transaction, $newType, $newAmount, $newDescription, $newDate, $reason, $editedBy) {
            $accountId = $transaction->savings_account_id;

            $this->reverseTransaction($transaction, $reason, $editedBy);

            // Diambil ulang (bukan pakai relasi $transaction->savingsAccount
            // yang sudah di-cache) supaya deposit()/withdraw() di bawah
            // menghitung dari saldo TERBARU setelah reverseTransaction()
            // di atas, bukan saldo sebelum dibalik.
            $account = SavingsAccount::query()->lockForUpdate()->findOrFail($accountId);

            return $newType === 'setor'
                ? $this->deposit($account, $newAmount, $editedBy, $newDescription, null, $newDate)
                : $this->withdraw($account, $newAmount, $editedBy, $newDescription, null, $newDate);
        });
    }

    /**
     * Preview journal lines without persisting anything — used to render
     * the Journal Preview panel on the Teller form (DESIGN §Transaction Panel)
     * before the Teller confirms the transaction.
     *
     * @return array<int, array{account_code: string, account_name: string, debit: float, credit: float}>
     */
    public function previewLines(SavingsAccount $account, string $type, float $amount): array
    {
        $product = $account->savingsProduct;
        $cash = $this->cashAccount();
        $liability = $product->liabilityAccount;

        return $type === 'setor'
            ? [
                ['account_code' => $cash->code, 'account_name' => $cash->name, 'debit' => $amount, 'credit' => 0],
                ['account_code' => $liability->code, 'account_name' => $liability->name, 'debit' => 0, 'credit' => $amount],
            ]
            : [
                ['account_code' => $liability->code, 'account_name' => $liability->name, 'debit' => $amount, 'credit' => 0],
                ['account_code' => $cash->code, 'account_name' => $cash->name, 'debit' => 0, 'credit' => $amount],
            ];
    }

    /**
     * Akun kas lawan transaksi Teller — SELALU akun kas cabang KSP (lihat
     * DEFAULT_BRANCH_CODE), bukan akun kas konsolidasi 1101 lagi, dan
     * bukan per-cabang rekening (lihat penjelasan di DEFAULT_BRANCH_CODE
     * kenapa itu tidak dipakai untuk Simpanan).
     *
     * Public supaya TellerController bisa menampilkan akun kas ini di
     * halaman Teller & Buka Rekening — permintaan staf untuk bisa
     * memverifikasi akun kas lawan SEBELUM transaksi diproses, bukan cuma
     * lewat panel "Preview Jurnal" setelah submit.
     */
    public function cashAccount(): ChartOfAccount
    {
        $kspBranch = Branch::query()->where('code', self::DEFAULT_BRANCH_CODE)->first();

        return $kspBranch?->cashAccount
            ?? ChartOfAccount::query()->where('code', self::FALLBACK_CASH_ACCOUNT_CODE)->firstOrFail();
    }

    private function generateAccountNumber(SavingsProduct $product): string
    {
        do {
            $candidate = strtoupper($product->code).'-'.now()->format('ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (SavingsAccount::query()->where('account_number', $candidate)->exists());

        return $candidate;
    }
}
