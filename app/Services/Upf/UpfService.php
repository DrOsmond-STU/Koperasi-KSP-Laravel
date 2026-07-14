<?php

namespace App\Services\Upf;

use App\Exceptions\Upf\FeeBillingException;
use App\Models\ChartOfAccount;
use App\Models\FeeBilling;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\Tenant;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * Penagihan & pembayaran iuran kios/tenant (PRD §10, Task 2.3). Setiap
 * tagihan & pembayaran menjurnal otomatis lewat JournalEngine — tidak ada
 * logika posting terpisah di sini.
 */
class UpfService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(private readonly JournalEngine $journalEngine) {}

    public function generateBilling(
        Tenant $tenant,
        FeeType $feeType,
        string $period,
        int $createdBy,
        ?float $meterStart = null,
        ?float $meterEnd = null,
    ): FeeBilling {
        if (FeeBilling::query()->where('tenant_id', $tenant->id)->where('fee_type_id', $feeType->id)->where('period', $period)->exists()) {
            throw FeeBillingException::alreadyBilled($period);
        }

        if ($feeType->isMeterBased()) {
            if ($meterStart === null || $meterEnd === null) {
                throw FeeBillingException::meterReadingRequired();
            }

            if ($meterEnd < $meterStart) {
                throw FeeBillingException::meterEndBeforeStart();
            }

            $amount = round(($meterEnd - $meterStart) * (float) $feeType->tariff, 2);
        } else {
            $amount = (float) $feeType->tariff;
        }

        return DB::transaction(function () use ($tenant, $feeType, $period, $meterStart, $meterEnd, $amount, $createdBy) {
            $entry = $this->journalEngine->post([
                'branch_id' => $tenant->branch_id,
                'entry_date' => now()->toDateString(),
                'description' => "Tagihan {$feeType->name} kios {$tenant->code} periode {$period}",
                'created_by' => $createdBy,
                'source' => $tenant,
                'lines' => [
                    ['chart_of_account_id' => $feeType->coa_receivable_account_id, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $feeType->coa_revenue_account_id, 'debit' => 0, 'credit' => $amount],
                ],
            ]);

            return FeeBilling::query()->create([
                'tenant_id' => $tenant->id,
                'fee_type_id' => $feeType->id,
                'period' => $period,
                'meter_start' => $meterStart,
                'meter_end' => $meterEnd,
                'amount' => $amount,
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
            ]);
        });
    }

    public function recordPayment(FeeBilling $billing, float $amount, int $createdBy): FeePayment
    {
        if ($billing->status === 'lunas') {
            throw FeeBillingException::billingAlreadyPaid();
        }

        $remaining = $billing->remainingAmount();

        if (bccomp((string) $amount, (string) $remaining, 2) > 0) {
            throw FeeBillingException::paymentExceedsRemaining((string) $remaining, (string) $amount);
        }

        return DB::transaction(function () use ($billing, $amount, $createdBy, $remaining) {
            $feeType = $billing->feeType;

            $entry = $this->journalEngine->post([
                'branch_id' => $billing->tenant->branch_id,
                'entry_date' => now()->toDateString(),
                'description' => "Pembayaran {$feeType->name} kios {$billing->tenant->code} periode {$billing->period}",
                'created_by' => $createdBy,
                'source' => $billing,
                'lines' => [
                    ['chart_of_account_id' => $this->cashAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $feeType->coa_receivable_account_id, 'debit' => 0, 'credit' => $amount],
                ],
            ]);

            $newPaidAmount = round((float) $billing->paid_amount + $amount, 2);
            $billing->update([
                'paid_amount' => $newPaidAmount,
                'status' => bccomp((string) $amount, (string) $remaining, 2) === 0 ? 'lunas' : 'sebagian',
            ]);

            return FeePayment::query()->create([
                'fee_billing_id' => $billing->id,
                'amount' => $amount,
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
            ]);
        });
    }

    private function cashAccountId(): int
    {
        return ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->value('id');
    }
}
