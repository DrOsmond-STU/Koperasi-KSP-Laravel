<?php

namespace Tests\Feature\Reporting;

use App\Models\ChartOfAccount;
use App\Models\CooperativeEvent;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the 5 new PDF+Excel export routes added for screens that had no
 * export before: Bagan Akun, Daftar Barang, Daftar Supplier, Daftar
 * Kalender, Daftar Aktiva Tetap. Each is gated by the same permission as
 * its index() action, so a role without .read access must be forbidden.
 */
class ListExportsTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function adminSistem(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function teller(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_chart_of_account_export_pdf_and_excel(): void
    {
        $admin = $this->adminSistem();
        ChartOfAccount::factory()->create(['code' => '9500', 'name' => 'Akun Ekspor Uji']);

        $pdf = $this->actingAs($admin)->get(route('admin.master.chart-of-accounts.export-pdf'));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');

        $excel = $this->actingAs($admin)->get(route('admin.master.chart-of-accounts.export-excel'));
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $excel->headers->get('content-type'));

        $teller = $this->teller();
        $this->actingAs($teller)->get(route('admin.master.chart-of-accounts.export-pdf'))->assertForbidden();
    }

    public function test_product_export_pdf_and_excel(): void
    {
        $manajer = $this->manajer();
        Product::factory()->create(['name' => 'Barang Ekspor Uji']);

        $pdf = $this->actingAs($manajer)->get(route('admin.master.products.export-pdf'));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');

        $excel = $this->actingAs($manajer)->get(route('admin.master.products.export-excel'));
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $excel->headers->get('content-type'));

        $teller = $this->teller();
        $this->actingAs($teller)->get(route('admin.master.products.export-pdf'))->assertForbidden();
    }

    public function test_supplier_export_pdf_and_excel(): void
    {
        $manajer = $this->manajer();
        Supplier::factory()->create(['name' => 'Supplier Ekspor Uji']);

        $pdf = $this->actingAs($manajer)->get(route('admin.master.suppliers.export-pdf'));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');

        $excel = $this->actingAs($manajer)->get(route('admin.master.suppliers.export-excel'));
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $excel->headers->get('content-type'));

        $teller = $this->teller();
        $this->actingAs($teller)->get(route('admin.master.suppliers.export-pdf'))->assertForbidden();
    }

    public function test_calendar_event_export_pdf_and_excel(): void
    {
        $manajer = $this->manajer();
        CooperativeEvent::factory()->create(['title' => 'Kegiatan Ekspor Uji']);

        $pdf = $this->actingAs($manajer)->get(route('admin.kalender.kegiatan.export-pdf'));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');

        $excel = $this->actingAs($manajer)->get(route('admin.kalender.kegiatan.export-excel'));
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $excel->headers->get('content-type'));

        $teller = $this->teller();
        $this->actingAs($teller)->get(route('admin.kalender.kegiatan.export-pdf'))->assertForbidden();
    }

    public function test_fixed_asset_export_pdf_and_excel(): void
    {
        $manajer = $this->manajer();
        $category = FixedAssetCategory::factory()->create();
        FixedAsset::factory()->create(['fixed_asset_category_id' => $category->id, 'name' => 'Aktiva Ekspor Uji']);

        $pdf = $this->actingAs($manajer)->get(route('admin.aktiva-tetap.export-pdf'));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');

        $excel = $this->actingAs($manajer)->get(route('admin.aktiva-tetap.export-excel'));
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $excel->headers->get('content-type'));

        $teller = $this->teller();
        $this->actingAs($teller)->get(route('admin.aktiva-tetap.export-pdf'))->assertForbidden();
    }
}
