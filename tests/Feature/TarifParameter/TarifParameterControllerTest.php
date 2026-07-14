<?php

namespace Tests\Feature\TarifParameter;

use App\Models\AuditLog;
use App\Models\LoanProduct;
use App\Models\SavingsProduct;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md AUD-02 (koreksi tarif tercatat before -> after) and
 * E2E-05 (perubahan tarif berlaku di simulasi berikutnya, bukan transaksi
 * lama — non-retroactive).
 */
class TarifParameterControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        return $user;
    }

    public function test_updating_savings_parameters_records_before_after_in_audit_log(): void
    {
        $user = $this->manajer();
        $product = SavingsProduct::factory()->create(['admin_fee' => 1000]);

        $this->actingAs($user)->put(route('admin.tarif-parameter.savings.update', $product), [
            'admin_fee' => 2500,
            'admin_fee_period' => 'bulanan',
        ])->assertRedirect(route('admin.tarif-parameter.index'));

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => SavingsProduct::class,
            'auditable_id' => $product->id,
            'action' => 'update',
        ]);

        $log = AuditLog::query()->where('auditable_id', $product->id)->where('action', 'update')->firstOrFail();
        $this->assertEquals(1000, (float) $log->before['admin_fee']);
        $this->assertEquals(2500, (float) $log->after['admin_fee']);
    }

    public function test_backdated_rate_effective_date_is_rejected(): void
    {
        $user = $this->manajer();
        $product = SavingsProduct::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.tarif-parameter.savings.rate', $product), [
            'rate_percentage' => 3.5,
            'effective_from' => now()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors(['effective_from']);
    }

    public function test_new_rate_applies_going_forward_without_altering_past_resolution(): void
    {
        $user = $this->manajer();
        $product = SavingsProduct::factory()->create();
        // Factory already seeds a rate of 2.5% effective a year ago.

        $pastDate = Carbon::now()->subMonths(6);
        $oldRateBefore = $product->rateAt($pastDate)?->rate_percentage;

        $this->actingAs($user)->post(route('admin.tarif-parameter.savings.rate', $product), [
            'rate_percentage' => 4.0,
            'effective_from' => now()->toDateString(),
        ])->assertRedirect(route('admin.tarif-parameter.index'));

        $product->refresh();

        $this->assertEquals($oldRateBefore, $product->rateAt($pastDate)?->rate_percentage);
        $this->assertEquals('4.000', $product->rateAt(now())->rate_percentage);
    }

    public function test_updating_loan_parameters_records_audit_trail(): void
    {
        $user = $this->manajer();
        $product = LoanProduct::factory()->create(['provision_fee_percentage' => 1.0]);

        $this->actingAs($user)->put(route('admin.tarif-parameter.loan.update', $product), [
            'min_plafon' => $product->min_plafon,
            'max_plafon' => $product->max_plafon,
            'provision_fee_percentage' => 2.5,
        ])->assertRedirect(route('admin.tarif-parameter.index'));

        $log = AuditLog::query()->where('auditable_id', $product->id)->where('auditable_type', LoanProduct::class)->where('action', 'update')->firstOrFail();
        $this->assertEquals(1.0, (float) $log->before['provision_fee_percentage']);
        $this->assertEquals(2.5, (float) $log->after['provision_fee_percentage']);
    }

    public function test_role_without_permission_cannot_update_parameters(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        $product = SavingsProduct::factory()->create();

        $this->actingAs($user)->put(route('admin.tarif-parameter.savings.update', $product), [
            'admin_fee' => 5000,
        ])->assertForbidden();
    }
}
