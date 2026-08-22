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
 * Pencatatan retribusi harian (UPF).
 *
 * Ada dua mode transaksi:
 *  - UMUM     : petugas memilih SATU jenis retribusi (percentage = 100%).
 *               Jurnal = 1 baris kredit (akun pendapatan jenis tsb) + 1
 *               baris debit ke akun kas KAS AO RIDWAN (UPF). Tidak ada
 *               pembagian otomatis.
 *  - ANGGOTA  : total dipecah otomatis ke seluruh jenis retribusi "split"
 *               (percentage < 100, jumlah persentasenya = 100) via
 *               RetributionSplitCalculator, mirror pola SHU/UPF lama.
 *
 * Jurnal LAWAN (debit) untuk kedua mode diarahkan ke akun kas khusus
 * petugas UPF (code 1101600 - KAS AO RIDWAN (UPF)) — fallback ke akun
 * kas umum (1101) hanya bila 1101600 belum ada (misal DB sangat lama
 * yang belum ke-migrate).
 */
class RetributionService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101600';

    private const FALLBACK_CASH_ACCOUNT_CODE = '1101';

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
        ?RetributionType $retributionType = null,
    ): RetributionTransaction {
        $entryDate = $date ?? now();
        $isAnggota = $payerType === 'anggota';

        if ($isAnggota) {
            $activeSplitTypes = $this->splitTypes();
            $this->assertHasActiveSplitTypes($activeSplitTypes);
            $this->assertSplitTypesFullyAllocated($activeSplitTypes);
            $this->assertAllHaveRevenueAccount($activeSplitTypes);
            $useTypes = $activeSplitTypes;
            $selectedType = null;
        } else {
            $selectedType = $retributionType?->fresh() ?? $retributionType;
            $this->assertUmumType($selectedType);
            $useTypes = new Collection([$selectedType]);
        }

        return DB::transaction(function () use (
            $branchId, $payerType, $payerName, $member, $totalAmount,
            $paymentMethod, $createdBy, $description, $entryDate,
            $useTypes, $isAnggota, $selectedType,
        ) {
            $totalCents = (int) round($totalAmount * 100);

            if ($isAnggota) {
                $splits = RetributionSplitCalculator::split(
                    $totalCents,
                    $useTypes->map(fn (RetributionType $type) => ['id' => $type->id, 'percentage' => $type->percentage])->all(),
                );
                $splitsByTypeId = collect($splits)->keyBy('id');
            } else {
                // Umum → seluruh nilai masuk ke satu jenis retribusi terpilih.
                $splitsByTypeId = collect([
                    $selectedType->id => ['id' => $selectedType->id, 'amount_cents' => $totalCents],
                ]);
            }

            $creditLines = $useTypes->map(fn (RetributionType $type) => [
                'chart_of_account_id' => $type->coa_revenue_account_id,
                'debit' => 0,
                'credit' => $splitsByTypeId[$type->id]['amount_cents'] / 100,
            ])->all();

            $lines = [
                ...$creditLines,
                ['chart_of_account_id' => $this->cashAccountId(), 'debit' => $totalCents / 100, 'credit' => 0],
            ];

            $resolvedPayerName = $isAnggota ? $member->name : $payerName;

            $description = $isAnggota
                ? "Retribusi UPF (Anggota) — {$resolvedPayerName} ({$useTypes->count()} jenis retribusi)"
                : "Retribusi UPF (Umum) — {$resolvedPayerName} / {$selectedType->name}";

            $entry = $this->journalEngine->post([
                'branch_id' => $branchId,
                'entry_date' => $entryDate->format('Y-m-d'),
                'description' => $description,
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
                'retribution_type_id' => $isAnggota ? null : $selectedType->id,
                'total_amount' => $totalCents / 100,
                'payment_method' => $paymentMethod,
                'description' => $description,
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
            ]);

            foreach ($useTypes as $type) {
                $transaction->lines()->create([
                    'retribution_type_id' => $type->id,
                    'retribution_type_name' => $type->name,
                    'percentage_applied' => $isAnggota ? $type->percentage : 100,
                    'chart_of_account_id' => $type->coa_revenue_account_id,
                    'amount' => $splitsByTypeId[$type->id]['amount_cents'] / 100,
                ]);
            }

            return $transaction->load('lines');
        });
    }

    /**
     * Ambil semua jenis retribusi aktif yang persentasenya < 100 —
     * inilah baris "split" untuk transaksi Anggota.
     *
     * @return Collection<int, RetributionType>
     */
    private function splitTypes(): Collection
    {
        return RetributionType::query()
            ->where('is_active', true)
            ->where('percentage', '<', 100)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, RetributionType>  $activeTypes
     */
    private function assertHasActiveSplitTypes(Collection $activeTypes): void
    {
        if ($activeTypes->isEmpty()) {
            throw RetributionException::noActiveSplitTypes();
        }
    }

    /**
     * @param  Collection<int, RetributionType>  $activeTypes
     */
    private function assertSplitTypesFullyAllocated(Collection $activeTypes): void
    {
        // Persentase disimpan sebagai DECIMAL(5,2) — precisi 2 desimal.
        // Menghitung di ranah "basis points" (nilai×100) memberi integer
        // eksak sehingga Σpersentase = 100.00 setara Σbp = 10000 tanpa
        // ketergantungan pada ekstensi bcmath dan tanpa risiko float
        // rounding.
        $totalBasisPoints = $activeTypes->sum(fn (RetributionType $type) => (int) round(((float) $type->percentage) * 100));

        if ($totalBasisPoints !== 10000) {
            $total = number_format($totalBasisPoints / 100, 2, '.', '');
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

    private function assertUmumType(?RetributionType $type): void
    {
        if ($type === null) {
            throw RetributionException::umumTypeMissing();
        }

        if (! $type->is_active) {
            throw RetributionException::umumTypeInactive($type->name);
        }

        // Basis-point comparison (percentage×100) — hindari bcmath & float.
        if ((int) round(((float) $type->percentage) * 100) !== 10000) {
            throw RetributionException::umumTypeNotFull($type->name, (string) $type->percentage);
        }

        if ($type->coa_revenue_account_id === null) {
            throw RetributionException::missingRevenueAccount($type->name);
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
        $id = ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->value('id');

        if ($id !== null) {
            return $id;
        }

        // Fallback (backward compat) — production DB yang belum ke-migrate
        // untuk baris 1101600 akan tetap bisa memposting ke akun kas umum.
        return ChartOfAccount::query()->where('code', self::FALLBACK_CASH_ACCOUNT_CODE)->value('id');
    }
}
