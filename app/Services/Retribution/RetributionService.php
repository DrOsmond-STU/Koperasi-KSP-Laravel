<?php

namespace App\Services\Retribution;

use App\Exceptions\Retribution\RetributionException;
use App\Models\ChartOfAccount;
use App\Models\Member;
use App\Models\RetributionTransaction;
use App\Models\RetributionType;
use App\Services\Accounting\JournalEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan retribusi harian (UPF) — satu nilai total dipecah otomatis ke
 * seluruh jenis retribusi aktif sesuai persentase (RetributionSplitCalculator)
 * lalu dijurnal sekaligus lewat JournalEngine (single writer, tidak ada
 * logika posting terpisah di sini — pola sama seperti UpfService/PosSaleService).
 */
class RetributionService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(private readonly JournalEngine $journalEngine) {}

    public function record(
        int $branchId,
        string $payerType,
        ?string $payerName,
        ?Member $member,
        float $totalAmount,
        string $paymentMethod,
        int $createdBy,
        ?string $description = null,
        ?\DateTimeInterface $date = null,
    ): RetributionTransaction {
        $activeTypes = RetributionType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->assertHasActiveTypes($activeTypes);
        $this->assertFullyAllocated($activeTypes);
        $this->assertAllHaveRevenueAccount($activeTypes);

        $entryDate = $date ?? now();

        return DB::transaction(function () use (
            $branchId, $payerType, $payerName, $member, $totalAmount,
            $paymentMethod, $createdBy, $description, $entryDate, $activeTypes,
        ) {
            $totalCents = (int) round($totalAmount * 100);

            $splits = RetributionSplitCalculator::split(
                $totalCents,
                $activeTypes->map(fn (RetributionType $type) => ['id' => $type->id, 'percentage' => $type->percentage])->all(),
            );
            $splitsByTypeId = collect($splits)->keyBy('id');

            $creditLines = $activeTypes->map(fn (RetributionType $type) => [
                'chart_of_account_id' => $type->coa_revenue_account_id,
                'debit' => 0,
                'credit' => $splitsByTypeId[$type->id]['amount_cents'] / 100,
            ])->all();

            $lines = [
                ...$creditLines,
                ['chart_of_account_id' => $this->cashAccountId(), 'debit' => $totalCents / 100, 'credit' => 0],
            ];

            $resolvedPayerName = $payerType === 'anggota' ? $member->name : $payerName;

            $entry = $this->journalEngine->post([
                'branch_id' => $branchId,
                'entry_date' => $entryDate->format('Y-m-d'),
                'description' => "Retribusi UPF — {$resolvedPayerName} ({$activeTypes->count()} jenis retribusi)",
                'created_by' => $createdBy,
                'lines' => $lines,
            ]);

            $transaction = RetributionTransaction::query()->create([
                'branch_id' => $branchId,
                'transaction_number' => $this->generateTransactionNumber(),
                'transaction_date' => $entryDate->format('Y-m-d'),
                'payer_type' => $payerType,
                'payer_name' => $resolvedPayerName,
                'member_id' => $member?->id,
                'total_amount' => $totalCents / 100,
                'payment_method' => $paymentMethod,
                'description' => $description,
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
            ]);

            foreach ($activeTypes as $type) {
                $transaction->lines()->create([
                    'retribution_type_id' => $type->id,
                    'retribution_type_name' => $type->name,
                    'percentage_applied' => $type->percentage,
                    'chart_of_account_id' => $type->coa_revenue_account_id,
                    'amount' => $splitsByTypeId[$type->id]['amount_cents'] / 100,
                ]);
            }

            return $transaction->load('lines');
        });
    }

    /**
     * @param  Collection<int, RetributionType>  $activeTypes
     */
    private function assertHasActiveTypes(Collection $activeTypes): void
    {
        if ($activeTypes->isEmpty()) {
            throw RetributionException::noActiveTypes();
        }
    }

    /**
     * @param  Collection<int, RetributionType>  $activeTypes
     */
    private function assertFullyAllocated(Collection $activeTypes): void
    {
        $total = $activeTypes->reduce(
            fn (string $carry, RetributionType $type) => bcadd($carry, (string) $type->percentage, 2),
            '0.00',
        );

        if (bccomp($total, '100.00', 2) !== 0) {
            throw RetributionException::percentagesNotFullyAllocated($total);
        }
    }

    /**
     * @param  Collection<int, RetributionType>  $activeTypes
     */
    private function assertAllHaveRevenueAccount(Collection $activeTypes): void
    {
        $offender = $activeTypes->first(fn (RetributionType $type) => $type->coa_revenue_account_id === null);

        if ($offender !== null) {
            throw RetributionException::missingRevenueAccount($offender->name);
        }
    }

    /**
     * Pembatalan transaksi retribusi yang sudah diposting — baris asli
     * (header + lines) tidak pernah dihapus, hanya ditandai dibatalkan.
     * Baris `retribution_transaction_lines` sengaja TIDAK dibalik satu-satu
     * (totalnya sudah tercermin utuh di satu jurnal pembalik).
     */
    public function reverseTransaction(RetributionTransaction $transaction, string $reason, int $cancelledBy): RetributionTransaction
    {
        if ($transaction->isCancelled()) {
            throw RetributionException::alreadyCancelled();
        }

        return DB::transaction(function () use ($transaction, $reason, $cancelledBy) {
            $reversalEntry = $this->journalEngine->reverse($transaction->journalEntry, $reason, $cancelledBy);

            $transaction->update([
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
                'cancellation_reason' => $reason,
                'reversal_journal_entry_id' => $reversalEntry->id,
            ]);

            return $transaction->fresh();
        });
    }

    private function generateTransactionNumber(): string
    {
        do {
            $candidate = 'RB-'.now()->format('ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (RetributionTransaction::query()->where('transaction_number', $candidate)->exists());

        return $candidate;
    }

    private function cashAccountId(): int
    {
        return ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->value('id');
    }
}
