<?php

namespace Tests\Feature\Loans;

use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Loans\LoanApprovalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Membatalkan PENGAJUAN yang belum dicairkan.
 *
 * Jalan buntu yang ditutup di sini nyata dan terjadi 16 Sep 2026: aturan
 * satu-orang-satu-suara berlaku untuk menyetujui maupun menolak, sedangkan
 * "tolak" adalah satu-satunya jalan keluar sebuah pengajuan. Penyetuju yang
 * sudah terlanjur memberi suara lalu menyadari pengajuannya salah — ganda,
 * atau tanggalnya keliru — tidak punya cara apa pun menghilangkannya, dan
 * pengajuan itu menggantung di antrian.
 *
 * Pembatalan bukan suara: ia penarikan pengajuan, dibatasi ke pembuatnya
 * atau admin_sistem/manajer, dan tidak menyentuh jurnal karena pinjaman
 * yang belum cair memang belum punya jurnal.
 */
class LoanApplicationCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function pengurus(string $role = 'manajer'): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole($role);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function pengajuan(?User $pembuat = null): Loan
    {
        return Loan::factory()->create([
            'branch_id' => Branch::factory()->create()->id,
            'created_by' => ($pembuat ?? User::factory()->create())->id,
            'required_approval_count' => 2,
            'principal_amount' => 30_000_000,
            'status' => 'diajukan',
            'submitted_at' => '2026-08-13',
        ]);
    }

    /** Inti masalahnya: penyetuju yang sudah memberi suara tetap bisa menarik pengajuannya. */
    public function test_an_approver_who_already_voted_can_still_cancel_the_application(): void
    {
        $manajer = $this->pengurus();
        $loan = $this->pengajuan();

        LoanApproval::query()->create([
            'loan_id' => $loan->id,
            'approved_by' => $manajer->id,
            'decision' => 'setuju',
            'decided_at' => now(),
        ]);

        $this->actingAs($manajer)
            ->post(route('admin.pinjaman.batalkan-pengajuan', $loan), ['reason' => 'Pengajuan ganda'])
            ->assertRedirect(route('admin.pinjaman.index'));

        $loan->refresh();
        $this->assertSame('dibatalkan', $loan->status);
        $this->assertSame('Pengajuan ganda', $loan->cancellation_reason);
        $this->assertSame($manajer->id, $loan->cancelled_by);
        $this->assertNotNull($loan->cancelled_at);
    }

    /** Suara yang sudah masuk adalah catatan audit — tidak dihapus oleh pembatalan. */
    public function test_existing_approvals_are_kept_as_a_record(): void
    {
        $manajer = $this->pengurus();
        $loan = $this->pengajuan();

        LoanApproval::query()->create([
            'loan_id' => $loan->id,
            'approved_by' => $manajer->id,
            'decision' => 'setuju',
            'decided_at' => now(),
        ]);

        app(LoanApprovalService::class)->cancelApplication($loan, 'Salah input', $manajer->id);

        $this->assertSame(1, LoanApproval::query()->where('loan_id', $loan->id)->count());
    }

    public function test_the_creator_can_cancel_their_own_application(): void
    {
        $staf = $this->pengurus('petugas_kredit');
        $loan = $this->pengajuan($staf);

        $this->actingAs($staf)
            ->post(route('admin.pinjaman.batalkan-pengajuan', $loan), ['reason' => 'Salah anggota'])
            ->assertRedirect();

        $this->assertSame('dibatalkan', $loan->fresh()->status);
    }

    public function test_someone_else_cannot_cancel_it(): void
    {
        $loan = $this->pengajuan();

        $this->actingAs($this->pengurus('petugas_kredit'))
            ->post(route('admin.pinjaman.batalkan-pengajuan', $loan), ['reason' => 'Coba-coba'])
            ->assertForbidden();

        $this->assertSame('diajukan', $loan->fresh()->status);
    }

    public function test_a_reason_is_required(): void
    {
        $manajer = $this->pengurus();
        $loan = $this->pengajuan();

        $this->actingAs($manajer)
            ->post(route('admin.pinjaman.batalkan-pengajuan', $loan), [])
            ->assertSessionHasErrors('reason');

        $this->assertSame('diajukan', $loan->fresh()->status);
    }

    /** Pinjaman yang sudah cair punya jalurnya sendiri — yang membalik jurnal. */
    public function test_a_disbursed_loan_is_not_cancelled_through_this_path(): void
    {
        $manajer = $this->pengurus();
        $loan = $this->pengajuan();
        $loan->update(['status' => 'dicairkan']);

        $this->actingAs($manajer)
            ->post(route('admin.pinjaman.batalkan-pengajuan', $loan), ['reason' => 'Salah'])
            ->assertRedirect();

        $this->assertSame('dicairkan', $loan->fresh()->status, 'Jalur ini tidak boleh menyentuh pinjaman yang sudah cair.');
    }

    public function test_cancelling_twice_is_rejected(): void
    {
        $manajer = $this->pengurus();
        $loan = $this->pengajuan();

        app(LoanApprovalService::class)->cancelApplication($loan, 'Pertama', $manajer->id);

        $this->expectExceptionMessage('sudah dibatalkan');
        app(LoanApprovalService::class)->cancelApplication($loan->fresh(), 'Kedua', $manajer->id);
    }

    /** Pengajuan yang dibatalkan harus hilang dari antrian. */
    public function test_a_cancelled_application_leaves_the_queue(): void
    {
        $manajer = $this->pengurus();
        $loan = $this->pengajuan();

        $this->actingAs($manajer)->get(route('admin.pinjaman.index'))
            ->assertOk()
            ->assertSee($loan->loan_number);

        app(LoanApprovalService::class)->cancelApplication($loan, 'Ganda', $manajer->id);

        $this->actingAs($manajer)->get(route('admin.pinjaman.index'))
            ->assertOk()
            ->assertDontSee($loan->loan_number);
    }

    /**
     * Menyembunyikan pengajuan yang ditarik tidak boleh ikut menyembunyikan
     * pinjaman yang benar-benar cair.
     *
     * Saringannya semula "harus punya tanggal cair", dan itu menghapus
     * pinjaman berstatus dicairkan yang disbursed_at-nya kosong dari layar —
     * padahal uangnya sudah keluar. Yang boleh diuji tanggal cairnya hanya
     * baris yang berstatus dibatalkan.
     */
    public function test_a_disbursed_loan_without_a_disbursement_date_is_still_listed(): void
    {
        $manajer = $this->pengurus();
        $loan = $this->pengajuan();
        $loan->forceFill(['status' => 'dicairkan', 'disbursed_at' => null])->save();

        $this->actingAs($manajer)->get(route('admin.pinjaman.index'))
            ->assertOk()
            ->assertSee($loan->loan_number);
    }

    /** Pinjaman yang cair lalu dibatalkan tetap tercatat di daftar pencairan. */
    public function test_a_loan_cancelled_after_disbursement_stays_listed(): void
    {
        $manajer = $this->pengurus();
        $loan = $this->pengajuan();
        $loan->forceFill([
            'status' => 'dibatalkan',
            'disbursed_at' => '2026-08-13',
            'cancelled_at' => now(),
        ])->save();

        $this->actingAs($manajer)->get(route('admin.pinjaman.index'))
            ->assertOk()
            ->assertSee($loan->loan_number);
    }
}
