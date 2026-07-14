<?php

namespace Tests\Feature\Upf;

use App\Models\FeeType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Upf\UpfService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateUpfPeriodicBillingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_command_bills_every_active_tenant_for_every_active_flat_fee_type(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin_sistem');

        $tenants = Tenant::factory()->count(2)->create(['status' => 'aktif']);
        Tenant::factory()->create(['status' => 'tutup']);

        $flatFeeType = FeeType::factory()->create(['tariff' => 50000]);
        FeeType::factory()->perMeter()->create();
        FeeType::factory()->create(['is_active' => false]);

        $this->artisan('upf:generate-tagihan-periodik', ['period' => '2026-07'])
            ->assertExitCode(0);

        $this->assertDatabaseCount('fee_billings', 2);
        foreach ($tenants as $tenant) {
            $this->assertDatabaseHas('fee_billings', [
                'tenant_id' => $tenant->id,
                'fee_type_id' => $flatFeeType->id,
                'period' => '2026-07',
                'created_by' => $admin->id,
            ]);
        }
    }

    public function test_command_skips_tenants_already_billed_for_the_period(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin_sistem');

        $tenant = Tenant::factory()->create(['status' => 'aktif']);
        $feeType = FeeType::factory()->create(['tariff' => 50000]);
        app(UpfService::class)->generateBilling($tenant, $feeType, '2026-07', $admin->id);

        $this->artisan('upf:generate-tagihan-periodik', ['period' => '2026-07'])
            ->assertExitCode(0);

        $this->assertDatabaseCount('fee_billings', 1);
    }

    public function test_command_fails_without_an_admin_sistem_user(): void
    {
        $this->artisan('upf:generate-tagihan-periodik')->assertExitCode(1);
    }
}
