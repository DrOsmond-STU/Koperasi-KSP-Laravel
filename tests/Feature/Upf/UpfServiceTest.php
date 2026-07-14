<?php

namespace Tests\Feature\Upf;

use App\Exceptions\Upf\FeeBillingException;
use App\Models\FeeType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Upf\UpfService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md LED-01 for UPF billing/payment (Task 2.3).
 */
class UpfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_flat_billing_creates_balanced_journal_with_tariff_amount(): void
    {
        $tenant = Tenant::factory()->create();
        $feeType = FeeType::factory()->create(['tariff' => 50000]);
        $user = User::factory()->create();

        $billing = app(UpfService::class)->generateBilling($tenant, $feeType, '2026-01', $user->id);

        $this->assertEquals(50000, (float) $billing->amount);
        $entry = $billing->journalEntry;
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));
    }

    public function test_meter_based_billing_computes_amount_from_usage(): void
    {
        $tenant = Tenant::factory()->create();
        $feeType = FeeType::factory()->perMeter()->create(['tariff' => 1500]);
        $user = User::factory()->create();

        $billing = app(UpfService::class)->generateBilling($tenant, $feeType, '2026-01', $user->id, meterStart: 100, meterEnd: 150);

        $this->assertEquals(75000, (float) $billing->amount); // (150-100) * 1500
    }

    public function test_meter_based_billing_without_readings_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $feeType = FeeType::factory()->perMeter()->create();
        $user = User::factory()->create();

        $this->expectException(FeeBillingException::class);
        app(UpfService::class)->generateBilling($tenant, $feeType, '2026-01', $user->id);
    }

    public function test_duplicate_billing_for_same_period_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $feeType = FeeType::factory()->create();
        $user = User::factory()->create();

        app(UpfService::class)->generateBilling($tenant, $feeType, '2026-01', $user->id);

        $this->expectException(FeeBillingException::class);
        app(UpfService::class)->generateBilling($tenant, $feeType, '2026-01', $user->id);
    }

    public function test_payment_within_remaining_amount_updates_status(): void
    {
        $tenant = Tenant::factory()->create();
        $feeType = FeeType::factory()->create(['tariff' => 50000]);
        $user = User::factory()->create();

        $billing = app(UpfService::class)->generateBilling($tenant, $feeType, '2026-01', $user->id);
        app(UpfService::class)->recordPayment($billing, 20000, $user->id);

        $this->assertEquals('sebagian', $billing->fresh()->status);
        $this->assertEquals(30000, $billing->fresh()->remainingAmount());

        app(UpfService::class)->recordPayment($billing->fresh(), 30000, $user->id);
        $this->assertEquals('lunas', $billing->fresh()->status);
    }

    public function test_payment_exceeding_remaining_amount_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $feeType = FeeType::factory()->create(['tariff' => 50000]);
        $user = User::factory()->create();

        $billing = app(UpfService::class)->generateBilling($tenant, $feeType, '2026-01', $user->id);

        $this->expectException(FeeBillingException::class);
        app(UpfService::class)->recordPayment($billing, 60000, $user->id);
    }
}
