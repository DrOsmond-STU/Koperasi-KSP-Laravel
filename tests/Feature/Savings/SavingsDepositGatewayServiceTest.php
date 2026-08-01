<?php

namespace Tests\Feature\Savings;

use App\Exceptions\Payment\PaymentGatewayException;
use App\Models\SavingsAccount;
use App\Models\SavingsDepositRequest;
use App\Models\User;
use App\Services\Savings\SavingsDepositGatewayService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Portal Anggota's "Setor Simpanan Mandiri" — unlike a withdrawal request,
 * no staff approval step: the Xendit webhook alone triggers
 * SavingsService::deposit(). These tests never hit a real Xendit server —
 * Http::fake() stands in for it.
 */
class SavingsDepositGatewayServiceTest extends TestCase
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

    public function test_request_creates_a_pending_row_with_invoice_details(): void
    {
        $this->fakeInvoiceResponse();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $user = User::factory()->create();

        $depositRequest = app(SavingsDepositGatewayService::class)->request($account, 150000, $user->id);

        $this->assertEquals('menunggu_pembayaran', $depositRequest->status);
        $this->assertEquals('inv_test_1', $depositRequest->xendit_invoice_id);
        $this->assertEquals('https://checkout.xendit.co/web/inv_test_1', $depositRequest->xendit_invoice_url);
        $this->assertStringStartsWith('SETOR-', $depositRequest->external_id);
        $this->assertEquals($account->member_id, $depositRequest->member_id);
    }

    public function test_request_marks_row_gagal_when_gateway_call_fails(): void
    {
        Http::fake(['*/v2/invoices' => Http::response(['message' => 'error'], 400)]);
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $user = User::factory()->create();

        $this->expectException(PaymentGatewayException::class);

        try {
            app(SavingsDepositGatewayService::class)->request($account, 150000, $user->id);
        } finally {
            $this->assertDatabaseHas('savings_deposit_requests', ['status' => 'gagal']);
        }
    }

    public function test_webhook_paid_credits_the_account_via_savings_service(): void
    {
        $this->fakeInvoiceResponse();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $user = User::factory()->create();
        $service = app(SavingsDepositGatewayService::class);

        $depositRequest = $service->request($account, 150000, $user->id);
        $service->handleWebhookPaid($depositRequest->external_id, 150000);

        $depositRequest->refresh();
        $this->assertEquals('dibayar', $depositRequest->status);
        $this->assertNotNull($depositRequest->savings_transaction_id);
        $this->assertNotNull($depositRequest->paid_at);
        $this->assertEquals(150000, (float) $account->fresh()->balance);
    }

    public function test_duplicate_webhook_delivery_does_not_double_credit(): void
    {
        $this->fakeInvoiceResponse();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $user = User::factory()->create();
        $service = app(SavingsDepositGatewayService::class);

        $depositRequest = $service->request($account, 150000, $user->id);
        $service->handleWebhookPaid($depositRequest->external_id, 150000);
        $service->handleWebhookPaid($depositRequest->external_id, 150000);

        $this->assertEquals(150000, (float) $account->fresh()->balance);
        $this->assertDatabaseCount('savings_transactions', 1);
    }

    public function test_webhook_with_mismatched_amount_does_not_credit(): void
    {
        $this->fakeInvoiceResponse();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $user = User::factory()->create();
        $service = app(SavingsDepositGatewayService::class);

        $depositRequest = $service->request($account, 150000, $user->id);
        $service->handleWebhookPaid($depositRequest->external_id, 99999);

        $this->assertEquals('menunggu_pembayaran', $depositRequest->fresh()->status);
        $this->assertEquals(0, (float) $account->fresh()->balance);
    }

    public function test_webhook_with_unknown_external_id_is_a_silent_no_op(): void
    {
        app(SavingsDepositGatewayService::class)->handleWebhookPaid('SETOR-tidak-ada', 150000);

        $this->assertDatabaseCount('savings_transactions', 0);
    }
}
