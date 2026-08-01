<?php

namespace Tests\Feature\Api;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Loans\LoanRepaymentGatewayService;
use App\Services\Savings\SavingsDepositGatewayService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Xendit Invoice callback — the only source of truth that a payment
 * completed. Verified via a shared token (X-Callback-Token), not HMAC.
 */
class XenditWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        Config::set('services.xendit.webhook_token', 'test-webhook-token');
        Config::set('services.xendit.secret_key', 'xnd_development_test_key');
        Http::fake(['*/v2/invoices' => Http::response(['id' => 'inv_1', 'invoice_url' => 'https://checkout.xendit.co/web/inv_1'], 200)]);
    }

    public function test_request_with_wrong_token_is_rejected(): void
    {
        $response = $this->postJson('/api/webhooks/xendit', [
            'external_id' => 'SETOR-anything', 'status' => 'PAID', 'amount' => 100000,
        ], ['x-callback-token' => 'wrong-token']);

        $response->assertForbidden();
    }

    public function test_unknown_external_id_is_acknowledged_not_errored(): void
    {
        $response = $this->postJson('/api/webhooks/xendit', [
            'external_id' => 'SOMETHING-ELSE', 'status' => 'PAID', 'amount' => 100000,
        ], ['x-callback-token' => 'test-webhook-token']);

        $response->assertOk();
    }

    public function test_non_paid_status_is_ignored(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $user = User::factory()->create();
        $depositRequest = app(SavingsDepositGatewayService::class)->request($account, 150000, $user->id);

        $response = $this->postJson('/api/webhooks/xendit', [
            'external_id' => $depositRequest->external_id, 'status' => 'PENDING', 'amount' => 150000,
        ], ['x-callback-token' => 'test-webhook-token']);

        $response->assertOk();
        $this->assertEquals(0, (float) $account->fresh()->balance);
    }

    public function test_setor_prefixed_external_id_routes_to_savings_deposit_and_credits_balance(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $user = User::factory()->create();
        $depositRequest = app(SavingsDepositGatewayService::class)->request($account, 150000, $user->id);

        $response = $this->postJson('/api/webhooks/xendit', [
            'external_id' => $depositRequest->external_id, 'status' => 'PAID', 'amount' => 150000,
        ], ['x-callback-token' => 'test-webhook-token']);

        $response->assertOk();
        $this->assertEquals(150000, (float) $account->fresh()->balance);
    }

    public function test_angsuran_prefixed_external_id_routes_to_loan_repayment_and_pays_installment(): void
    {
        $product = LoanProduct::factory()->create();
        $loan = Loan::factory()->create(['loan_product_id' => $product->id, 'status' => 'dicairkan']);
        LoanSchedule::query()->create([
            'loan_id' => $loan->id, 'installment_number' => 1, 'due_date' => now()->addMonth(),
            'principal_amount' => 1000000, 'interest_amount' => 100000, 'total_amount' => 1100000,
            'paid_amount' => 0, 'status' => 'belum_bayar',
        ]);
        $user = User::factory()->create();
        $repaymentRequest = app(LoanRepaymentGatewayService::class)->request($loan, 1100000, $user->id);

        $response = $this->postJson('/api/webhooks/xendit', [
            'external_id' => $repaymentRequest->external_id, 'status' => 'PAID', 'amount' => 1100000,
        ], ['x-callback-token' => 'test-webhook-token']);

        $response->assertOk();
        $this->assertEquals('lunas', $loan->fresh()->status);
    }
}
