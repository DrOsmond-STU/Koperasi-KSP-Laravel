<?php

namespace Tests\Feature\Upf;

use App\Models\FeeBilling;
use App\Models\FeeType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md E2E-05: Petugas UPF generate tagihan → input
 * pembayaran → jurnal iuran terbentuk, status tagihan ter-update.
 */
class UpfControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function petugasUpf(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_full_billing_to_payment_flow(): void
    {
        $officer = $this->petugasUpf();
        $tenant = Tenant::factory()->create();
        $feeType = FeeType::factory()->create(['tariff' => 75000]);

        $billResponse = $this->actingAs($officer)->post(route('staf.upf.tagihan'), [
            'tenant_id' => $tenant->id,
            'fee_type_id' => $feeType->id,
            'period' => '2026-02',
        ]);
        $billResponse->assertRedirect(route('staf.upf.index'));

        $this->assertDatabaseHas('fee_billings', [
            'tenant_id' => $tenant->id,
            'fee_type_id' => $feeType->id,
            'period' => '2026-02',
            'amount' => 75000,
            'status' => 'belum_bayar',
        ]);

        $billing = FeeBilling::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $payResponse = $this->actingAs($officer)->post(route('staf.upf.pembayaran'), [
            'fee_billing_id' => $billing->id,
            'amount' => 75000,
        ]);
        $payResponse->assertRedirect(route('staf.upf.index'));

        $this->assertEquals('lunas', $billing->fresh()->status);

        // Outstanding list should no longer show a fully-paid billing.
        $index = $this->actingAs($officer)->get(route('staf.upf.index'));
        $index->assertOk();
        $index->assertDontSee('2026-02');
    }

    public function test_role_without_permission_cannot_access_upf(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');

        $this->actingAs($user)
            ->get(route('staf.upf.index'))
            ->assertForbidden();
    }
}
