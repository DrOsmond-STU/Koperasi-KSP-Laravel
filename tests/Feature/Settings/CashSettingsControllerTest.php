<?php

namespace Tests\Feature\Settings;

use App\Models\ChartOfAccount;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Settings\CashSettingsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Akun kas pencairan pinjaman diatur di Pengaturan → Kas Cabang, supaya
 * pengurus bisa menggantinya sendiri tanpa menyentuh berkas di server.
 */
class CashSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function kas(string $code, string $name, bool $postable = true): ChartOfAccount
    {
        return ChartOfAccount::factory()->create([
            'code' => $code,
            'name' => $name,
            'type' => 'ASET',
            'normal_balance' => 'DEBIT',
            'statement' => 'NERACA',
            'is_postable' => $postable,
        ]);
    }

    public function test_admin_can_set_the_loan_disbursement_cash_account(): void
    {
        $admin = $this->admin();
        $kas = $this->kas('1101200', 'KAS KECIL (USP)');

        $response = $this->actingAs($admin)->put(route('admin.pengaturan.kas-pencairan.update'), [
            'loan_disbursement_account_id' => $kas->id,
        ]);

        $response->assertRedirect(route('admin.pengaturan.kas-cabang.index'));
        $this->assertEquals($kas->id, app(CashSettingsService::class)->current()->loan_disbursement_account_id);
    }

    /** Dikosongkan = kembali ke resolusi per cabang seperti sebelumnya. */
    public function test_it_can_be_cleared_again(): void
    {
        $admin = $this->admin();
        $kas = $this->kas('1101200', 'KAS KECIL (USP)');
        app(CashSettingsService::class)->update($kas->id, $admin->id);

        $this->actingAs($admin)->put(route('admin.pengaturan.kas-pencairan.update'), [
            'loan_disbursement_account_id' => '',
        ]);

        $this->assertNull(app(CashSettingsService::class)->current()->loan_disbursement_account_id);
    }

    /**
     * Akun header ditolak di sini, bukan dibiarkan lolos sampai seseorang
     * menyetujui pinjaman dan pencairannya gagal.
     */
    public function test_a_header_account_is_rejected(): void
    {
        $admin = $this->admin();
        $header = $this->kas('11010', 'KAS', postable: false);

        $response = $this->actingAs($admin)->put(route('admin.pengaturan.kas-pencairan.update'), [
            'loan_disbursement_account_id' => $header->id,
        ]);

        $response->assertSessionHasErrors('loan_disbursement_account_id');
        $this->assertNull(app(CashSettingsService::class)->current()->loan_disbursement_account_id);
    }

    public function test_a_role_without_master_data_update_cannot_change_it(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $kas = $this->kas('1101200', 'KAS KECIL (USP)');

        $this->actingAs($user)->put(route('admin.pengaturan.kas-pencairan.update'), [
            'loan_disbursement_account_id' => $kas->id,
        ])->assertForbidden();
    }
}
