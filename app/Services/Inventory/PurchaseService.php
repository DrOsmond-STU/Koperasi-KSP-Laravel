<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\PurchaseApprovalException;
use App\Exceptions\Inventory\PurchasePaymentException;
use App\Models\ApprovalThreshold;
use App\Models\ChartOfAccount;
use App\Models\PurchasePayment;
use App\Models\PurchaseTransaction;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * Transaksi Pembelian Barang + Pembayaran Hutang Supplier (PRD §13.4,
 * Task 3.3). Di bawah ambang nilai (`approval_thresholds` modul
 * "pembelian") transaksi langsung diposting (stok + jurnal) saat dibuat;
 * di atasnya, transaksi menunggu approval dari orang lain (AUTH-09/12)
 * sebelum StockLedgerEngine/JournalEngine dipanggil.
 */
class PurchaseService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(
        private readonly StockLedgerEngine $stockLedgerEngine,
        private readonly JournalEngine $journalEngine,
    ) {}

    /**
     * @param  array<int, array{product_id: int, qty: string, unit_price: string}>  $items
     */
    public function submit(
        Supplier $supplier,
        int $branchId,
        string $paymentMethod,
        array $items,
        int $createdBy,
        ?\DateTimeInterface $date = null,
    ): PurchaseTransaction {
        $total = '0.00';
        $rows = [];

        foreach ($items as $item) {
            $subtotal = bcmul((string) $item['qty'], (string) $item['unit_price'], 2);
            $total = bcadd($total, $subtotal, 2);
            $rows[] = [
                'product_id' => $item['product_id'],
                'qty' => $item['qty'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $subtotal,
            ];
        }

        $needsApproval = bccomp($total, ApprovalThreshold::forModule('pembelian'), 2) > 0;

        return DB::transaction(function () use ($supplier, $branchId, $paymentMethod, $rows, $total, $createdBy, $date, $needsApproval) {
            // Every purchase starts "menunggu_approval"; post() below flips
            // it to "diposting" immediately when no approval is required.
            $purchase = PurchaseTransaction::query()->create([
                'branch_id' => $branchId,
                'supplier_id' => $supplier->id,
                'purchase_number' => $this->generatePurchaseNumber(),
                'purchase_date' => ($date ?? now())->format('Y-m-d'),
                'payment_method' => $paymentMethod,
                'total_amount' => $total,
                'status' => 'menunggu_approval',
                'payment_status' => $paymentMethod === 'kredit' ? 'belum_lunas' : null,
                'created_by' => $createdBy,
            ]);

            foreach ($rows as $row) {
                $purchase->items()->create($row);
            }

            if (! $needsApproval) {
                $this->post($purchase, $createdBy);
            }

            return $purchase->fresh('items');
        });
    }

    public function approve(PurchaseTransaction $purchase, User $approver): PurchaseTransaction
    {
        $this->guardCanDecide($purchase, $approver);

        return DB::transaction(function () use ($purchase, $approver) {
            $this->post($purchase, $purchase->created_by);
            $purchase->update(['approved_by' => $approver->id]);

            return $purchase->fresh(['items', 'journalEntry']);
        });
    }

    public function reject(PurchaseTransaction $purchase, User $approver): PurchaseTransaction
    {
        $this->guardCanDecide($purchase, $approver);

        $purchase->update([
            'status' => 'ditolak',
            'approved_by' => $approver->id,
        ]);

        return $purchase->fresh();
    }

    public function pay(PurchaseTransaction $purchase, string $amount, int $createdBy, ?\DateTimeInterface $date = null): PurchasePayment
    {
        if ($purchase->status !== 'diposting') {
            throw PurchasePaymentException::notYetPosted();
        }

        if ($purchase->payment_status === 'lunas') {
            throw PurchasePaymentException::alreadyPaid();
        }

        $remaining = $purchase->remainingAmount();

        if (bccomp($amount, $remaining, 2) > 0) {
            throw PurchasePaymentException::paymentExceedsRemaining($remaining, $amount);
        }

        return DB::transaction(function () use ($purchase, $amount, $createdBy, $date, $remaining) {
            $entry = $this->journalEngine->post([
                'branch_id' => $purchase->branch_id,
                'entry_date' => ($date ?? now())->format('Y-m-d'),
                'description' => "Pembayaran hutang supplier {$purchase->supplier->name} — {$purchase->purchase_number}",
                'created_by' => $createdBy,
                'source' => $purchase,
                'lines' => [
                    ['chart_of_account_id' => $purchase->supplier->coa_payable_account_id, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $this->cashAccount()->id, 'debit' => 0, 'credit' => $amount],
                ],
            ]);

            $newPaid = bcadd((string) $purchase->paid_amount, $amount, 2);
            $purchase->update([
                'paid_amount' => $newPaid,
                'payment_status' => bccomp($amount, $remaining, 2) === 0 ? 'lunas' : 'sebagian',
            ]);

            return PurchasePayment::query()->create([
                'purchase_transaction_id' => $purchase->id,
                'amount' => $amount,
                'payment_date' => ($date ?? now())->format('Y-m-d'),
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
            ]);
        });
    }

    private function guardCanDecide(PurchaseTransaction $purchase, User $approver): void
    {
        if ($purchase->status !== 'menunggu_approval') {
            throw PurchaseApprovalException::notPending($purchase->status);
        }

        if ($purchase->created_by === $approver->id) {
            throw PurchaseApprovalException::selfApproval();
        }
    }

    private function post(PurchaseTransaction $purchase, int $userId): void
    {
        $purchase->load('items.product');

        $lines = [];

        foreach ($purchase->items as $item) {
            $this->stockLedgerEngine->receive(
                $item->product,
                $purchase->branch_id,
                (string) $item->qty,
                (string) $item->unit_price,
                'pembelian',
                $userId,
                $purchase,
                $purchase->purchase_date,
            );

            $lines[] = [
                'chart_of_account_id' => $item->product->coa_inventory_account_id,
                'debit' => (string) $item->subtotal,
                'credit' => 0,
            ];
        }

        $creditAccountId = $purchase->payment_method === 'kredit'
            ? $purchase->supplier->coa_payable_account_id
            : $this->cashAccount()->id;

        $lines[] = [
            'chart_of_account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => (string) $purchase->total_amount,
        ];

        $entry = $this->journalEngine->post([
            'branch_id' => $purchase->branch_id,
            'entry_date' => $purchase->purchase_date,
            'description' => "Pembelian barang dari {$purchase->supplier->name} — {$purchase->purchase_number}",
            'created_by' => $userId,
            'source' => $purchase,
            'lines' => $lines,
        ]);

        $purchase->update([
            'status' => 'diposting',
            'journal_entry_id' => $entry->id,
            'payment_status' => $purchase->payment_method === 'kredit' ? 'belum_lunas' : 'lunas',
            'paid_amount' => $purchase->payment_method === 'kredit' ? 0 : $purchase->total_amount,
        ]);
    }

    private function generatePurchaseNumber(): string
    {
        do {
            $candidate = 'PB-'.now()->format('ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (PurchaseTransaction::query()->where('purchase_number', $candidate)->exists());

        return $candidate;
    }

    private function cashAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->firstOrFail();
    }
}
