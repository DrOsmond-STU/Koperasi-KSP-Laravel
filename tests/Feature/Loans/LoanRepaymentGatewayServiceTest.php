<?php

namespace Tests\Feature\Loans;

use App\Exceptions\Loans\LoanRepaymentException;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\User;
use App\Services\Loans\LoanRepaymentGatewayService;
use App\Services\Loans\LoanRepaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Portal Anggota's "Bayar Angsuran Mandiri" — gateway wiring on top of
 * LoanRepaymentService. The `perlu_rekonsiliasi_manual` case is the one
 * behaviour unique to this service: the outstanding amount can legitimately
 * change between invoice creation and webhook arrival.
 */
class LoanRepaymentGatewayServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        Config::set('services.xendit.secret_key', 'xnd_development_test_key');
    }

    private function fakeInvoiceResponse(): void
    {
        Http::fake(['*/v2/invoices' => Http::response([
            'id' => 'inv_test_1',
            'invoice_url' => 'https://checkout.xendit.co/web/inv_test_1',
        ], 200)]);
    }

    private function disbursedLoanWithOneInstallment(): Loan
    {
        $product = LoanProduct::factory()->create();
        $loan = Loan::factory()->create(['loan_product_id' => $product->id, 'status' => 'dicairkan']);

        LoanSchedule::query()->create([
            'loan_id' => $loan->id, 'installment_number' => 1, 'due_date' => now()->addMonth(),
            'principal_amount' => 1000000, 'interest_amount' => 100000, 'total_amount' => 1100000,
            'paid_amount' => 0, 'status' => 'belum_bayar',
        ]);

        return $loan->fresh();
    }

    public function test_request_creates_a_pending_row_with_invoice_details(): void
    {
        $this->fakeInvoiceResponse();
        $loan = $this->disbursedLoanWithOneInstallment();
        $user = User::factory()->create();

        $repaymentRequest = app(LoanRepaymentGatewayService::class)->request($loan, 1100000, $user->id);

        $this->assertEquals('menunggu_pembayaran', $repaymentRequest->status);
        $this->assertEquals('inv_test_1', $repaymentRequest->xendit_invoice_id);
        $this->assertStringStartsWith('ANGSURAN-', $repaymentRequest->external_id);
    }

    public function test_request_exceeding_outstanding_is_rejected_before_calling_the_gateway(): void
    {
        $loan = $this->disbursedLoanWithOneInstallment();
        $user = User::factory()->create();

        $this->expectException(LoanRepaymentException::class);

        try {
            app(LoanRepaymentGatewayService::class)->request($loan, 5000000, $user->id);
        } finally {
            $this->assertDatabaseCount('loan_repayment_requests', 0);
            Http::assertNothingSent();
        }
    }

    public function test_webhook_paid_records_the_repayment_via_loan_repayment_service(): void
    {
        $this->fakeInvoiceResponse();
        $loan = $this->disbursedLoanWithOneInstallment();
        $user = User::factory()->create();
        $service = app(LoanRepaymentGatewayService::class);

        $repaymentRequest = $service->request($loan, 1100000, $user->id);
        $service->handleWebhookPaid($repaymentRequest->external_id, 1100000);

        $repaymentRequest->refresh();
        $this->assertEquals('dibayar', $repaymentRequest->status);
        $this->assertNotNull($repaymentRequest->loan_repayment_id);
        $this->assertEquals('lunas', $loan->fresh()->status);
    }

    public function test_duplicate_webhook_delivery_does_not_record_twice(): void
    {
        $this->fakeInvoiceResponse();
        $loan = $this->disbursedLoanWithOneInstallment();
        $user = User::factory()->create();
        $service = app(LoanRepaymentGatewayService::class);

        $repaymentRequest = $service->request($loan, 1100000, $user->id);
        $service->handleWebhookPaid($repaymentRequest->external_id, 1100000);
        $service->handleWebhookPaid($repaymentRequest->external_id, 1100000);

        $this->assertDatabaseCount('loan_repayments', 1);
    }

    public function test_outstanding_shrinking_before_webhook_arrives_flags_manual_reconciliation(): void
    {
        $this->fakeInvoiceResponse();
        $loan = $this->disbursedLoanWithOneInstallment();
        $user = User::factory()->create();
        $service = app(LoanRepaymentGatewayService::class);

        $repaymentRequest = $service->request($loan, 1100000, $user->id);

        // Another repayment lands on the same loan before the webhook arrives
        // (e.g. paid over the counter) — the outstanding this request was
        // sized against no longer exists.
        app(LoanRepaymentService::class)->recordPayment($loan, 1100000, $user->id);

        $service->handleWebhookPaid($repaymentRequest->external_id, 1100000);

        $this->assertEquals('perlu_rekonsiliasi_manual', $repaymentRequest->fresh()->status);
        $this->assertDatabaseCount('loan_repayments', 1);
    }
}
