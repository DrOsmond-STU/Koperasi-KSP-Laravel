<?php

namespace Tests\Feature\Reporting;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Product;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Inventory\StockLedgerEngine;
use App\Services\Reporting\LaporanRegistry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_role_can_view_hub_index(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        $response = $this->actingAs($user)->get(route('admin.laporan.index'));

        $response->assertOk();
        $response->assertSee('Menu Laporan');
        $response->assertSee('Data Anggota');
    }

    public function test_authorized_role_can_view_a_report_datatable_with_data(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $member = Member::factory()->create(['name' => 'Anggota Uji Laporan']);

        $response = $this->actingAs($user)->get(route('admin.laporan.show', 'anggota'));

        $response->assertOk();
        $response->assertSee('Anggota Uji Laporan');
        $response->assertSee($member->member_number);
    }

    public function test_unknown_module_returns_404(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        $response = $this->actingAs($user)->get(route('admin.laporan.show', 'modul-tidak-ada'));

        $response->assertNotFound();
    }

    public function test_role_without_permission_cannot_view_hub(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');

        $response = $this->actingAs($user)->get(route('admin.laporan.index'));

        $response->assertForbidden();
    }

    public function test_role_without_permission_cannot_view_a_report(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');

        $response = $this->actingAs($user)->get(route('admin.laporan.show', 'anggota'));

        $response->assertForbidden();
    }

    public function test_export_pdf_returns_pdf_document(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        Member::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.laporan.export-pdf', 'anggota'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_excel_returns_xlsx_document(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        Member::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.laporan.export-excel', 'anggota'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_every_registered_module_renders_successfully(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        foreach (array_keys(LaporanRegistry::definitions()) as $module) {
            $this->actingAs($user)->get(route('admin.laporan.show', $module))->assertOk();
        }
    }

    /**
     * Kartu Persediaan Rincian format kartu buku besar: baris "Saldo Awal"
     * sintetis (belum ada migrasi saldo awal untuk produk ini) di depan
     * grup, mutasi riil di tengah, "Saldo Akhir" di penutup grup.
     */
    public function test_kartu_persediaan_renders_ledger_card_format(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $branch = Branch::factory()->create();
        $product = Product::factory()->create(['name' => 'Barang Kartu Uji']);
        app(StockLedgerEngine::class)->receive($product, $branch->id, '30', '5000', 'pembelian', $user->id);

        $response = $this->actingAs($user)->get(route('admin.laporan.show', 'persediaan_kartu'));

        $response->assertOk();
        $response->assertSee('Barang Kartu Uji');
        $response->assertSee('Saldo Awal');
        $response->assertSee('Saldo Akhir');
        $response->assertSee('Pembelian');
    }

    /** Modul dengan kolom tanggal menampilkan filter Periode ... s/d ...; modul tanpa kolom tanggal tidak. */
    public function test_period_filter_shown_only_for_modules_with_a_date_column(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $withDate = $this->actingAs($user)->get(route('admin.laporan.show', 'pinjaman'));
        $withDate->assertOk();
        $withDate->assertSee('class="dt-period"', false);
        $withDate->assertSee('data-date-column="disbursed_at"', false);

        $withoutDate = $this->actingAs($user)->get(route('admin.laporan.show', 'barang'));
        $withoutDate->assertOk();
        $withoutDate->assertDontSee('class="dt-period"', false);
    }
}
