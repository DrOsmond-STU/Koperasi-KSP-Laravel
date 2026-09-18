<?php

namespace Tests\Feature\Loans;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanBranchRepair;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Loans\LoanBranchRepairService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Memindahkan kegiatan pinjaman ke cabang yang benar.
 *
 * Keadaan nyata 18 Sep 2026: seluruh 6.425 angsuran dan 1.089 jurnalnya
 * tercatat di KPPD Pusat, membawa Rp 17.275.650 pada akun yang namanya
 * sendiri berbunyi PENDAPATAN JASA PINJAMAN (USP). Laba rugi USP karena itu
 * kosong dari pendapatan jasa.
 *
 * Yang paling penting dikunci di sini bukan "barisnya pindah", melainkan
 * bahwa PEMBUKUANNYA TIDAK BERGESER: jumlah jurnal, jumlah baris, dan total
 * debet/kredit harus sama persis sesudahnya. Memindahkan cabang pada jurnal
 * yang sudah diposting hanya boleh kalau sifat itu bisa dibuktikan.
 */
class LoanBranchRepairTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function pengurus(string $role = 'admin_sistem'): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole($role);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    /**
     * Pinjaman POS = tidak punya jurnal pencairan sendiri. Angsurannya
     * dijurnal dengan satu baris debet kas dan satu baris kredit pendapatan,
     * supaya invarian debet=kredit bisa diuji sungguhan.
     *
     * @return array{0: Loan, 1: LoanRepayment, 2: JournalEntry}
     */
    private function pinjamanPosBerangsur(Branch $asal): array
    {
        $loan = Loan::factory()->create(['branch_id' => $asal->id, 'status' => 'dicairkan']);

        // Dibuat langsung, bukan lewat factory: LoanRepayment tidak punya
        // factory, dan LoanRepaymentService menolak angsuran tanpa jadwal —
        // padahal yang diuji di sini cuma kolom cabangnya.
        $repayment = LoanRepayment::query()->create([
            'branch_id' => $asal->id,
            'loan_id' => $loan->id,
            'amount' => 150000,
            'principal_portion' => 0,
            'interest_portion' => 150000,
            'balance_after' => 0,
            'created_by' => User::factory()->create()->id,
        ]);

        $entry = JournalEntry::query()->create([
            'branch_id' => $asal->id,
            'entry_date' => '2026-08-20',
            'description' => 'Angsuran '.$loan->loan_number,
            'created_by' => User::factory()->create()->id,
            'source_type' => LoanRepayment::class,
            'source_id' => $repayment->id,
        ]);

        $kas = ChartOfAccount::query()->where('is_postable', true)->where('type', 'ASET')->firstOrFail();
        $pendapatan = ChartOfAccount::query()->where('is_postable', true)->where('type', 'PENDAPATAN')->firstOrFail();

        $entry->lines()->create(['chart_of_account_id' => $kas->id, 'debit' => 150000, 'credit' => 0]);
        $entry->lines()->create(['chart_of_account_id' => $pendapatan->id, 'debit' => 0, 'credit' => 150000]);

        return [$loan, $repayment, $entry];
    }

    private function usp(): Branch
    {
        return Branch::factory()->create(['code' => '002', 'name' => 'Unit Simpan Pinjam', 'is_active' => true]);
    }

    public function test_the_preview_counts_what_would_move_without_moving_it(): void
    {
        $pusat = Branch::factory()->create(['name' => 'KPPD Pusat']);
        $usp = $this->usp();
        [$loan, $repayment, $entry] = $this->pinjamanPosBerangsur($pusat);

        $pratinjau = app(LoanBranchRepairService::class)->pratinjau($usp->id);

        $this->assertSame(1, $pratinjau['loans']);
        $this->assertSame(1, $pratinjau['repayments']);
        $this->assertSame(1, $pratinjau['entries']);
        $this->assertSame(3, $pratinjau['total']);

        // Tidak satu pun benar-benar berpindah.
        $this->assertSame($pusat->id, $loan->fresh()->branch_id);
        $this->assertSame($pusat->id, $repayment->fresh()->branch_id);
        $this->assertSame($pusat->id, $entry->fresh()->branch_id);
    }

    public function test_the_preview_names_the_income_left_behind(): void
    {
        $pusat = Branch::factory()->create(['name' => 'KPPD Pusat']);
        $usp = $this->usp();
        $this->pinjamanPosBerangsur($pusat);

        $pendapatan = app(LoanBranchRepairService::class)->pratinjau($usp->id)['pendapatan'];

        $this->assertCount(1, $pendapatan);
        $this->assertEqualsWithDelta(150000, (float) $pendapatan->first()->neto, 0.01);
    }

    public function test_running_it_moves_every_row(): void
    {
        $pusat = Branch::factory()->create();
        $usp = $this->usp();
        [$loan, $repayment, $entry] = $this->pinjamanPosBerangsur($pusat);

        app(LoanBranchRepairService::class)->jalankan($usp->id, $this->pengurus()->id);

        $this->assertSame($usp->id, $loan->fresh()->branch_id);
        $this->assertSame($usp->id, $repayment->fresh()->branch_id);
        $this->assertSame($usp->id, $entry->fresh()->branch_id);
    }

    /** Inti seluruh berkas ini. */
    public function test_the_books_do_not_shift_at_all(): void
    {
        $pusat = Branch::factory()->create();
        $usp = $this->usp();
        $this->pinjamanPosBerangsur($pusat);
        $this->pinjamanPosBerangsur($pusat);

        $sebelum = [
            'entries' => DB::table('journal_entries')->count(),
            'lines' => DB::table('journal_lines')->count(),
            'debit' => (float) DB::table('journal_lines')->sum('debit'),
            'credit' => (float) DB::table('journal_lines')->sum('credit'),
        ];

        app(LoanBranchRepairService::class)->jalankan($usp->id, $this->pengurus()->id);

        $this->assertSame($sebelum['entries'], DB::table('journal_entries')->count());
        $this->assertSame($sebelum['lines'], DB::table('journal_lines')->count());
        $this->assertEqualsWithDelta($sebelum['debit'], (float) DB::table('journal_lines')->sum('debit'), 0.01);
        $this->assertEqualsWithDelta($sebelum['credit'], (float) DB::table('journal_lines')->sum('credit'), 0.01);
        $this->assertEqualsWithDelta(
            (float) DB::table('journal_lines')->sum('debit'),
            (float) DB::table('journal_lines')->sum('credit'),
            0.01,
        );
    }

    /** Pinjaman biasa punya jurnal pencairan sendiri — cabangnya sudah benar, jangan disentuh. */
    public function test_an_ordinary_loan_with_its_own_journal_is_left_alone(): void
    {
        $ksp = Branch::factory()->create(['name' => 'KSP']);
        $usp = $this->usp();

        $biasa = Loan::factory()->create(['branch_id' => $ksp->id, 'status' => 'dicairkan']);
        JournalEntry::query()->create([
            'branch_id' => $ksp->id,
            'entry_date' => '2026-08-06',
            'description' => 'Pencairan '.$biasa->loan_number,
            'created_by' => User::factory()->create()->id,
            'source_type' => Loan::class,
            'source_id' => $biasa->id,
        ]);

        app(LoanBranchRepairService::class)->pratinjau($usp->id);
        $pratinjau = app(LoanBranchRepairService::class)->pratinjau($usp->id);

        $this->assertSame(0, $pratinjau['loans'], 'Pinjaman berjurnal sendiri tidak boleh ikut dipindah.');
    }

    public function test_undo_returns_each_row_to_its_own_original_branch(): void
    {
        $pusat = Branch::factory()->create(['name' => 'Pusat']);
        $ksp = Branch::factory()->create(['name' => 'KSP']);
        $usp = $this->usp();

        [$dariPusat] = $this->pinjamanPosBerangsur($pusat);
        [$dariKsp] = $this->pinjamanPosBerangsur($ksp);

        $service = app(LoanBranchRepairService::class);
        $repair = $service->jalankan($usp->id, $this->pengurus()->id);

        $this->assertSame($usp->id, $dariPusat->fresh()->branch_id);
        $this->assertSame($usp->id, $dariKsp->fresh()->branch_id);

        $service->batalkan($repair, $this->pengurus()->id);

        // Bukan dikembalikan ke satu cabang seragam — masing-masing ke asalnya.
        $this->assertSame($pusat->id, $dariPusat->fresh()->branch_id);
        $this->assertSame($ksp->id, $dariKsp->fresh()->branch_id);
    }

    public function test_undoing_twice_is_rejected(): void
    {
        $pusat = Branch::factory()->create();
        $usp = $this->usp();
        $this->pinjamanPosBerangsur($pusat);

        $service = app(LoanBranchRepairService::class);
        $repair = $service->jalankan($usp->id, $this->pengurus()->id);
        $service->batalkan($repair, $this->pengurus()->id);

        $this->expectExceptionMessage('sudah dibatalkan');
        $service->batalkan($repair->fresh(), $this->pengurus()->id);
    }

    public function test_running_it_with_nothing_to_move_is_rejected(): void
    {
        $usp = $this->usp();
        $this->pinjamanPosBerangsur($usp);

        $this->expectExceptionMessage('Tidak ada baris yang perlu dipindahkan');
        app(LoanBranchRepairService::class)->jalankan($usp->id, $this->pengurus()->id);
    }

    public function test_the_screen_opens_and_shows_the_preview(): void
    {
        $pusat = Branch::factory()->create();
        $usp = $this->usp();
        $this->pinjamanPosBerangsur($pusat);

        $this->actingAs($this->pengurus())
            ->get(route('admin.pinjaman.perbaikan-cabang.form', ['branch_id' => $usp->id]))
            ->assertOk()
            ->assertSee('AKAN DIPINDAHKAN')
            ->assertSee('Perbaikan Cabang Pinjaman');
    }

    public function test_the_button_moves_the_rows_and_records_the_repair(): void
    {
        $pusat = Branch::factory()->create();
        $usp = $this->usp();
        [$loan] = $this->pinjamanPosBerangsur($pusat);

        $this->actingAs($this->pengurus())
            ->post(route('admin.pinjaman.perbaikan-cabang.apply'), [
                'branch_id' => $usp->id,
                'konfirmasi' => '1',
            ])
            ->assertRedirect();

        $this->assertSame($usp->id, $loan->fresh()->branch_id);
        $this->assertSame(1, LoanBranchRepair::query()->count());
    }

    /** Kotak konfirmasi memaksa pengurus melewati angkanya dulu. */
    public function test_without_ticking_the_confirmation_nothing_moves(): void
    {
        $pusat = Branch::factory()->create();
        $usp = $this->usp();
        [$loan] = $this->pinjamanPosBerangsur($pusat);

        $this->actingAs($this->pengurus())
            ->post(route('admin.pinjaman.perbaikan-cabang.apply'), ['branch_id' => $usp->id])
            ->assertSessionHasErrors('konfirmasi');

        $this->assertSame($pusat->id, $loan->fresh()->branch_id);
    }

    /** Pemeliharaan data induk — teller dan petugas kredit tidak boleh menjalankannya. */
    public function test_a_teller_cannot_run_it(): void
    {
        $pusat = Branch::factory()->create();
        $usp = $this->usp();
        [$loan] = $this->pinjamanPosBerangsur($pusat);

        $this->actingAs($this->pengurus('teller'))
            ->post(route('admin.pinjaman.perbaikan-cabang.apply'), [
                'branch_id' => $usp->id,
                'konfirmasi' => '1',
            ])
            ->assertForbidden();

        $this->assertSame($pusat->id, $loan->fresh()->branch_id);
    }
}
