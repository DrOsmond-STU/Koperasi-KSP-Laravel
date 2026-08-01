<?php

namespace Tests\Feature\Savings;

use App\Exceptions\Savings\InsufficientBalanceException;
use App\Exceptions\Savings\WithdrawalRequestException;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Savings\SavingsService;
use App\Services\Savings\SavingsWithdrawalRequestService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portal Anggota's "Pengajuan Penarikan Simpanan" — a request/approval
 * queue, not self-service money movement. Approving must delegate to the
 * existing SavingsService::withdraw() (the only writer of balance/
 * savings_transactions), never mutate balance itself.
 */
class SavingsWithdrawalRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_request_is_rejected_when_amount_exceeds_balance(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 100000]);
        $user = User::factory()->create();

        $this->expectException(InsufficientBalanceException::class);
        app(SavingsWithdrawalRequestService::class)->request($account, 200000, $user->id);
    }

    public function test_request_creates_a_pending_row(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $user = User::factory()->create();

        $request = app(SavingsWithdrawalRequestService::class)->request($account, 150000, $user->id);

        $this->assertEquals('menunggu', $request->status);
        $this->assertEquals($account->member_id, $request->member_id);
        $this->assertEquals(150000, (float) $request->amount);
        $this->assertDatabaseCount('savings_withdrawal_requests', 1);
    }

    public function test_approve_processes_the_withdrawal_via_savings_service(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $requester = User::factory()->create();
        $reviewer = User::factory()->create();

        $withdrawalRequest = app(SavingsWithdrawalRequestService::class)->request($account, 150000, $requester->id);
        $approved = app(SavingsWithdrawalRequestService::class)->approve($withdrawalRequest, $reviewer->id);

        $this->assertEquals('disetujui', $approved->status);
        $this->assertEquals($reviewer->id, $approved->reviewed_by);
        $this->assertNotNull($approved->savings_transaction_id);
        $this->assertEquals(350000, (float) $account->fresh()->balance);
        $this->assertDatabaseHas('savings_transactions', [
            'id' => $approved->savings_transaction_id,
            'type' => 'tarik',
            'amount' => '150000.00',
        ]);
    }

    public function test_approve_revalidates_balance_that_dropped_since_the_request_was_made(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 200000]);
        $requester = User::factory()->create();
        $reviewer = User::factory()->create();

        $withdrawalRequest = app(SavingsWithdrawalRequestService::class)->request($account, 150000, $requester->id);

        // Balance moves after the request was submitted (another withdrawal in between).
        app(SavingsService::class)->withdraw($account, 100000, $requester->id);

        $this->expectException(InsufficientBalanceException::class);
        app(SavingsWithdrawalRequestService::class)->approve($withdrawalRequest, $reviewer->id);
    }

    public function test_reject_marks_the_request_with_notes(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $requester = User::factory()->create();
        $reviewer = User::factory()->create();

        $withdrawalRequest = app(SavingsWithdrawalRequestService::class)->request($account, 150000, $requester->id);
        $rejected = app(SavingsWithdrawalRequestService::class)->reject($withdrawalRequest, $reviewer->id, 'Data tidak sesuai');

        $this->assertEquals('ditolak', $rejected->status);
        $this->assertEquals('Data tidak sesuai', $rejected->decision_notes);
        $this->assertEquals(500000, (float) $account->fresh()->balance);
    }

    public function test_deciding_an_already_decided_request_is_rejected(): void
    {
        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $requester = User::factory()->create();
        $reviewer = User::factory()->create();

        $withdrawalRequest = app(SavingsWithdrawalRequestService::class)->request($account, 150000, $requester->id);
        app(SavingsWithdrawalRequestService::class)->approve($withdrawalRequest, $reviewer->id);

        $this->expectException(WithdrawalRequestException::class);
        app(SavingsWithdrawalRequestService::class)->reject($withdrawalRequest->fresh(), $reviewer->id, 'Terlambat');
    }
}
