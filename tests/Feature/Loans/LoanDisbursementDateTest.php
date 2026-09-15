<?php

namespace Tests\Feature\Loans;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
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
 * Tanggal pencairan diisi penyetuju, bukan disimpulkan dari hari ini.
 *
 * Koperasi mencatat pinjaman lama secara susulan — akad Agustus baru masuk
 * sistem bulan berikutnya. Kalau tanggalnya diambil dari date() hari itu,
 * kas keluar tercatat di bulan yang salah dan seluruh jadwal angsurannya
 * bergeser berbulan-bulan.
 */
class LoanDisbursementDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function loan(User $creator): Loan
    {
        $kas = ChartOfAccount::factory()->create([
            'code' => '1101200', 'name' => 'KAS KECIL (USP)', 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'statement' => 'NERACA', 'is_postable' => true,
        ]);
        $branch = Branch::factory()->create(['cash_account_id' => $kas->id]);

        return Loan::factory()->create([
            'branch_id' => $branch->id,
            'created_by' => $creator->id,
            'required_approval_count' => 1,
            'principal_amount' => 30_000_000,
            'submitted_at' => '2026-08-04',
        ]);
    }

    /** Jurnal, tanggal cair, dan awal jadwal semuanya ikut tanggal yang diisi. */
    public function test_backdated_approval_drives_journal_disbursed_at_and_schedule(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        $loan = $this->loan($creator);

        app(LoanApprovalService::class)->approve($loan, $approver, null, '2026-08-07');

        $loan->refresh();
        $this->assertEquals('dicairkan', $loan->status);
        $this->assertEquals('2026-08-07', $loan->disbursed_at->toDateString());

        $entry = JournalEntry::query()
            ->where('source_type', Loan::class)
            ->where('source_id', $loan->id)
            ->firstOrFail();

        $this->assertEquals('2026-08-07', $entry->entry_date->toDateString());
        $this->assertStringContainsString('dicatat susulan', $entry->description);

        // Angsuran pertama jatuh sebulan setelah pencairan. Dihitung dari
        // 7 Agu berarti 7 Sep — kalau tanggalnya diambil dari hari
        // penginputan, jadwalnya akan bergeser sebulan penuh.
        $this->assertEquals(
            '2026-09-07',
            $loan->schedules()->orderBy('installment_number')->first()->due_date->toDateString(),
            'Jadwal angsuran harus dihitung dari tanggal pencairan, bukan hari penginputan.',
        );
    }

    /** Keputusan yang dicatat susulan tercatat pada tanggalnya sendiri. */
    public function test_the_approval_row_carries_the_same_date(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        $loan = $this->loan($creator);

        app(LoanApprovalService::class)->approve($loan, $approver, null, '2026-08-07');

        $this->assertEquals(
            '2026-08-07',
            LoanApproval::query()->where('loan_id', $loan->id)->firstOrFail()->decided_at->toDateString(),
        );
    }

    /** Tanpa tanggal, perilaku lama dipertahankan (pemanggil non-formulir). */
    public function test_without_a_date_it_falls_back_to_the_previous_behaviour(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        $loan = $this->loan($creator);

        app(LoanApprovalService::class)->approve($loan, $approver);

        $this->assertEquals('2026-08-04', $loan->fresh()->disbursed_at->toDateString());
    }

    private function approver(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    /** Menyetujui tanpa mengisi tanggal ditolak — tidak boleh diam-diam jadi hari ini. */
    public function test_approving_without_a_date_is_rejected(): void
    {
        $approver = $this->approver();
        $loan = $this->loan(User::factory()->create());

        $response = $this->actingAs($approver)->post(route('admin.pinjaman.decide', $loan), [
            'decision' => 'setuju',
        ]);

        $response->assertSessionHasErrors('disbursed_on');
        $this->assertEquals('diajukan', $loan->fresh()->status);
    }

    public function test_a_future_date_is_rejected(): void
    {
        $approver = $this->approver();
        $loan = $this->loan(User::factory()->create());

        $this->actingAs($approver)->post(route('admin.pinjaman.decide', $loan), [
            'decision' => 'setuju',
            'disbursed_on' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('disbursed_on');
    }

    /** Uang tidak bisa keluar sebelum pengajuannya ada. */
    public function test_a_date_before_the_application_is_rejected(): void
    {
        $approver = $this->approver();
        $loan = $this->loan(User::factory()->create());

        $this->actingAs($approver)->post(route('admin.pinjaman.decide', $loan), [
            'decision' => 'setuju',
            'disbursed_on' => '2026-08-01',
        ])->assertSessionHasErrors('disbursed_on');
    }

    /** Menolak tidak butuh tanggal. */
    public function test_rejecting_does_not_require_a_date(): void
    {
        $approver = $this->approver();
        $loan = $this->loan(User::factory()->create());

        $this->actingAs($approver)->post(route('admin.pinjaman.decide', $loan), [
            'decision' => 'tolak',
            'notes' => 'Tidak memenuhi syarat',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('ditolak', $loan->fresh()->status);
    }
}
