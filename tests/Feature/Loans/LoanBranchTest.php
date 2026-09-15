<?php

namespace Tests\Feature\Loans;

use App\Models\Branch;
use App\Models\CashSetting;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Loans\LoanApprovalService;
use App\Services\Loans\LoanBranchResolver;
use App\Services\Settings\CashSettingsService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Cabang pemilik transaksi pinjaman.
 *
 * Cabang pada transaksi dipakai untuk laba rugi per unit usaha, jadi yang
 * harus tercatat adalah unit yang MENJALANKAN pinjamannya — bukan unit
 * tempat anggotanya terdaftar. Sebelum ini cabangnya diambil dari
 * `members.branch_id`, sehingga di produksi tidak ada satu pun jurnal
 * pinjaman di cabang USP padahal seluruh kegiatan pinjaman milik USP.
 */
class LoanBranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        Cache::forget('cash_settings');
    }

    private function kas(): ChartOfAccount
    {
        return ChartOfAccount::factory()->create([
            'code' => '1101200', 'name' => 'KAS KECIL (USP)', 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'statement' => 'NERACA', 'is_postable' => true,
        ]);
    }

    private function aturCabangPinjaman(Branch $branch): void
    {
        CashSetting::query()->updateOrCreate(['id' => 1], ['loan_branch_id' => $branch->id]);
        Cache::forget('cash_settings');
    }

    public function test_without_the_setting_the_members_branch_is_kept(): void
    {
        $cabangAnggota = Branch::factory()->create();

        $this->assertSame(
            $cabangAnggota->id,
            app(LoanBranchResolver::class)->resolve($cabangAnggota->id),
        );
    }

    public function test_the_setting_overrides_the_members_branch(): void
    {
        $cabangAnggota = Branch::factory()->create();
        $usp = Branch::factory()->create(['code' => '002', 'name' => 'Unit Simpan Pinjam ( USP )']);
        $this->aturCabangPinjaman($usp);

        $this->assertSame($usp->id, app(LoanBranchResolver::class)->resolve($cabangAnggota->id));
    }

    /** Jurnal pencairan dan baris pinjamannya harus menunjuk cabang yang sama. */
    public function test_disbursement_books_the_journal_and_the_loan_to_the_configured_branch(): void
    {
        $kas = $this->kas();
        $cabangAnggota = Branch::factory()->create(['cash_account_id' => $kas->id, 'name' => 'KPPD Pusat']);
        $usp = Branch::factory()->create(['cash_account_id' => $kas->id, 'code' => '002', 'name' => 'Unit Simpan Pinjam ( USP )']);
        $this->aturCabangPinjaman($usp);

        $creator = User::factory()->create();
        $loan = Loan::factory()->create([
            'branch_id' => $cabangAnggota->id,
            'created_by' => $creator->id,
            'required_approval_count' => 1,
            'principal_amount' => 10_000_000,
            'submitted_at' => '2026-08-04',
        ]);

        app(LoanApprovalService::class)->approve($loan, User::factory()->create(), null, '2026-08-06');

        $entry = JournalEntry::query()
            ->where('source_type', Loan::class)
            ->where('source_id', $loan->id)
            ->firstOrFail();

        $this->assertSame($usp->id, $entry->branch_id, 'Jurnal pencairan harus masuk cabang yang diatur.');
        $this->assertSame($usp->id, $loan->fresh()->branch_id, 'Baris pinjaman ikut dibetulkan agar angsurannya tidak menyimpang.');
    }

    public function test_disbursement_keeps_the_loans_branch_when_the_setting_is_empty(): void
    {
        $kas = $this->kas();
        $cabang = Branch::factory()->create(['cash_account_id' => $kas->id]);

        $loan = Loan::factory()->create([
            'branch_id' => $cabang->id,
            'created_by' => User::factory()->create()->id,
            'required_approval_count' => 1,
            'principal_amount' => 5_000_000,
            'submitted_at' => '2026-08-04',
        ]);

        app(LoanApprovalService::class)->approve($loan, User::factory()->create(), null, '2026-08-06');

        $this->assertSame($cabang->id, $loan->fresh()->branch_id);
        $this->assertSame(
            $cabang->id,
            JournalEntry::query()->where('source_type', Loan::class)->where('source_id', $loan->id)->value('branch_id'),
        );
    }

    /** Pengajuan lewat layar staf ikut memakai cabang yang diatur. */
    public function test_an_application_is_recorded_in_the_configured_branch(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $cabangAnggota = Branch::factory()->create(['name' => 'Unit Pengelola Fasilitas ( UPF )']);
        $usp = Branch::factory()->create(['code' => '002', 'name' => 'Unit Simpan Pinjam ( USP )']);
        $this->aturCabangPinjaman($usp);

        $member = Member::factory()->create(['branch_id' => $cabangAnggota->id]);
        $product = LoanProduct::factory()->create([
            'min_plafon' => 1_000_000, 'max_plafon' => 50_000_000,
            'min_tenor_days' => 1, 'max_tenor_days' => 200,
        ]);

        $staf = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $staf->assignRole('petugas_kredit');
        UserBranchScope::query()->create(['user_id' => $staf->id, 'scope_type' => 'all']);

        $this->actingAs($staf)->post(route('staf.pengajuan-pinjaman.store'), [
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 10_000_000,
            'tenor_days' => 100,
        ]);

        $loan = Loan::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertSame($usp->id, $loan->branch_id, 'Pengajuan harus tercatat di unit yang menjalankan pinjaman, bukan cabang anggota.');
    }

    /** Menyimpan salah satu setelan tidak boleh mengosongkan yang lain. */
    public function test_saving_one_setting_keeps_the_other(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $kas = $this->kas();
        $usp = Branch::factory()->create(['code' => '002', 'name' => 'Unit Simpan Pinjam ( USP )']);

        $admin = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $admin->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $admin->id, 'scope_type' => 'all']);

        $this->actingAs($admin)
            ->put(route('admin.pengaturan.kas-pencairan.update'), ['loan_disbursement_account_id' => $kas->id])
            ->assertRedirect();

        $this->actingAs($admin)
            ->put(route('admin.pengaturan.cabang-pinjaman.update'), ['loan_branch_id' => $usp->id])
            ->assertRedirect();

        $setelan = app(CashSettingsService::class);

        $this->assertSame($usp->id, $setelan->loanBranchId());
        $this->assertSame($kas->id, $setelan->current()->loan_disbursement_account_id, 'Akun kas pencairan tidak boleh ikut terhapus.');
    }

    public function test_an_inactive_branch_is_rejected(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $mati = Branch::factory()->create(['is_active' => false]);
        $admin = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $admin->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $admin->id, 'scope_type' => 'all']);

        $this->actingAs($admin)
            ->put(route('admin.pengaturan.cabang-pinjaman.update'), ['loan_branch_id' => $mati->id])
            ->assertSessionHasErrors('loan_branch_id');
    }

    /** Master Cabang: controller dan viewnya sudah lama ada, rutenya yang hilang. */
    public function test_the_branch_master_screen_is_reachable(): void
    {
        $this->seed(RolePermissionSeeder::class);

        Branch::factory()->create(['code' => '002', 'name' => 'Unit Simpan Pinjam ( USP )']);

        $admin = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $admin->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $admin->id, 'scope_type' => 'all']);

        $this->actingAs($admin)->get(route('admin.master.branches.index'))
            ->assertOk()
            ->assertSee('002')
            ->assertSee('Unit Simpan Pinjam ( USP )');
    }
}
