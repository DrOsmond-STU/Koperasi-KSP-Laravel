<?php

namespace Tests\Feature\Loans;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Loans\LoanRepaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catat Angsuran (teller counter, cash) — the gap the user reported: the
 * only existing repayment path was Anggota\LoanRepaymentController (member
 * self-service through the Xendit gateway). This is a synchronous staff
 * path over the same LoanRepaymentService, no gateway involved.
 *
 * Sejak 26 Agu 2026 (instruksi KPPD Depok): staf menentukan sendiri
 * pembagian Pokok/Jasa/Denda per pembayaran (LoanRepaymentService::
 * recordManualPayment()), bukan dihitung otomatis dari sisa baris jadwal
 * lagi — lihat test_normal_installment_* untuk bukti default yang
 * disarankan TIDAK terpengaruh tunggakan/jadwal yang menggumpal.
 */
class StafLoanRepaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function petugasKredit(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_kredit');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function bendahara(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function cashAccountId(): int
    {
        return ChartOfAccount::query()->where('code', '1101')->firstOrFail()->id;
    }

    /** Three installments of Rp 1.000.000 pokok + Rp 100.000 jasa each. */
    private function disbursedLoanWithThreeInstallments(): Loan
    {
        $product = LoanProduct::factory()->create();
        $loan = Loan::factory()->create(['loan_product_id' => $product->id, 'status' => 'dicairkan', 'principal_amount' => 3000000]);

        for ($i = 1; $i <= 3; $i++) {
            LoanSchedule::query()->create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'due_date' => now()->addMonths($i),
                'principal_amount' => 1000000,
                'interest_amount' => 100000,
                'total_amount' => 1100000,
                'paid_amount' => 0,
                'status' => 'belum_bayar',
            ]);
        }

        return $loan->fresh();
    }

    public function test_role_without_pinjaman_create_cannot_access_the_screen(): void
    {
        $user = $this->bendahara();

        $this->actingAs($user)->get(route('staf.angsuran.create'))->assertForbidden();
    }

    public function test_petugas_kredit_can_preview_the_allocation(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();

        $response = $this->actingAs($user)->post(route('staf.angsuran.preview'), [
            'loan_id' => $loan->id,
            'principal_portion' => 1000000,
            'interest_portion' => 100000,
            'penalty_portion' => 0,
            'cash_account_id' => $this->cashAccountId(),
        ]);

        $response->assertOk();
        $response->assertSee('1.100.000', false);
        $response->assertSee($loan->loan_number);
    }

    /**
     * Laporan staf 26 Agu 2026: tampilkan akun COA (jurnal) di bawah tabel
     * Komponen pada halaman Konfirmasi Angsuran — supaya staf bisa lihat
     * akun kas didebit dan akun Piutang/Pendapatan Jasa/Piutang Denda yang
     * akan dikredit SEBELUM menekan "Konfirmasi & Simpan", bukan cuma
     * setelah pembayaran benar-benar terposting.
     */
    public function test_preview_shows_the_journal_lines_with_their_coa_accounts(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();
        $cashAccount = ChartOfAccount::query()->find($this->cashAccountId());
        $receivableAccount = $loan->loanProduct->receivableAccount;
        $interestAccount = $loan->loanProduct->interestIncomeAccount;
        $penaltyAccount = $loan->loanProduct->penaltyReceivableAccount;

        $response = $this->actingAs($user)->post(route('staf.angsuran.preview'), [
            'loan_id' => $loan->id,
            'principal_portion' => 1000000,
            'interest_portion' => 100000,
            'penalty_portion' => 50000,
            'cash_account_id' => $cashAccount->id,
        ]);

        $response->assertOk();
        $response->assertSeeInOrder([
            'Jurnal',
            $cashAccount->code.' — '.$cashAccount->name,
            $receivableAccount->code.' — '.$receivableAccount->name,
            $interestAccount->code.' — '.$interestAccount->name,
            $penaltyAccount->code.' — '.$penaltyAccount->name,
        ]);
    }

    public function test_petugas_kredit_can_record_a_full_installment_payment(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();

        $cashAccountId = $this->cashAccountId();

        $response = $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'principal_portion' => 1000000,
            'interest_portion' => 100000,
            'penalty_portion' => 0,
            'cash_account_id' => $cashAccountId,
        ]);

        $response->assertRedirect(route('staf.angsuran.create'));
        $this->assertDatabaseHas('loan_repayments', [
            'loan_id' => $loan->id,
            'amount' => '1100000.00',
            'principal_portion' => '1000000.00',
            'interest_portion' => '100000.00',
            'penalty_portion' => '0.00',
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('loan_schedules', [
            'loan_id' => $loan->id, 'installment_number' => 1, 'status' => 'lunas',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'chart_of_account_id' => $cashAccountId, 'debit' => '1100000.00',
        ]);
    }

    /**
     * Regresi: form Catat Angsuran sebelumnya tidak punya field tanggal sama
     * sekali — staf yang menyusulkan angsuran lama tidak bisa mencatat
     * tanggal pembayaran yang sebenarnya, jadi semuanya tercatat "hari ini".
     */
    public function test_petugas_kredit_can_backdate_a_repayment_via_paid_at(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();
        $paidOn = now()->subDays(5)->toDateString();

        $response = $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'principal_portion' => 1000000,
            'interest_portion' => 100000,
            'penalty_portion' => 0,
            'paid_at' => $paidOn,
            'cash_account_id' => $this->cashAccountId(),
        ]);

        $response->assertRedirect(route('staf.angsuran.create'));
        $this->assertDatabaseHas('loan_repayments', [
            'loan_id' => $loan->id,
            'paid_at' => $paidOn,
        ]);
    }

    public function test_overpayment_is_rejected_with_a_friendly_error(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();

        $response = $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'principal_portion' => 90000000,
            'interest_portion' => 9999999,
            'penalty_portion' => 0,
            'cash_account_id' => $this->cashAccountId(),
        ]);

        $response->assertSessionHasErrors('principal_portion');
        $this->assertDatabaseCount('loan_repayments', 0);
    }

    /** Instruksi KPPD Depok 26 Agu 2026: Pokok, Jasa, Denda tidak boleh ketiganya nol. */
    public function test_all_three_components_zero_is_rejected(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();

        $response = $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'principal_portion' => 0,
            'interest_portion' => 0,
            'penalty_portion' => 0,
            'cash_account_id' => $this->cashAccountId(),
        ]);

        $response->assertSessionHasErrors('principal_portion');
        $this->assertDatabaseCount('loan_repayments', 0);
    }

    public function test_role_without_pinjaman_create_cannot_record_a_payment(): void
    {
        $user = $this->bendahara();
        $loan = $this->disbursedLoanWithThreeInstallments();

        $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'principal_portion' => 1000000,
            'interest_portion' => 100000,
            'penalty_portion' => 0,
        ])->assertForbidden();
    }

    public function test_admin_pinjaman_index_links_to_print_schedule_and_catat_angsuran(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $loan = $this->disbursedLoanWithThreeInstallments();

        $response = $this->actingAs($user)->get(route('admin.pinjaman.index'));

        $response->assertOk();
        $response->assertSee(route('admin.print.loans.schedule', $loan), false);
        $response->assertSee(route('staf.angsuran.create', ['loan_id' => $loan->id]), false);
    }

    /**
     * Regresi: akun kas lawan angsuran sebelumnya diam-diam diambil dari
     * cabang si pinjaman sendiri (`Loan::branch_id`) — untuk pinjaman
     * anggota lama, cabang itu keliru tersimpan "KPPD Pusat", jadi jurnal
     * lawan angsuran salah posting ke kas KPPD Pusat, bukan kas cabang USP
     * (laporan staf 25 Agu 2026). Sekarang form Catat Angsuran menawarkan
     * dropdown "Akun Kas Penerima" yang defaultnya SELALU kas cabang USP
     * (dicari lewat nama cabang, bukan kode akun statis).
     */
    public function test_default_cash_account_offered_is_the_usp_branch_cash_account(): void
    {
        $user = $this->petugasKredit();
        $uspCashAccount = ChartOfAccount::query()->create([
            'code' => '1101400', 'name' => 'KAS AO RIDWAN (USP)', 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'is_postable' => true, 'statement' => 'NERACA',
        ]);
        Branch::factory()->create(['name' => 'Unit Simpan Pinjam ( USP )', 'cash_account_id' => $uspCashAccount->id]);

        $response = $this->actingAs($user)->get(route('staf.angsuran.create'));

        $response->assertOk();
        $response->assertSee('KAS AO RIDWAN (USP)');
        $response->assertSee('value="'.$uspCashAccount->id.'" selected', false);
    }

    /** Bagian "tapi bisa di rubah saat input data" dari laporan staf 25 Agu 2026. */
    public function test_staf_can_override_the_cash_account_when_recording_a_payment(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();
        $alternateCashAccount = ChartOfAccount::query()->create([
            'code' => '1101999', 'name' => 'KAS CABANG LAIN', 'type' => 'ASET',
            'normal_balance' => 'DEBIT', 'is_postable' => true, 'statement' => 'NERACA',
        ]);

        $response = $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'principal_portion' => 1000000,
            'interest_portion' => 100000,
            'penalty_portion' => 0,
            'cash_account_id' => $alternateCashAccount->id,
        ]);

        $response->assertRedirect(route('staf.angsuran.create'));
        $this->assertDatabaseHas('journal_lines', [
            'chart_of_account_id' => $alternateCashAccount->id, 'debit' => '1100000.00',
        ]);
    }

    /**
     * Instruksi KPPD Depok 26 Agu 2026: "perhitungan nya tetap normal
     * pinjaman di bagi lama pinjaman + % jasa" — TIDAK boleh melihat
     * tunggakan atau baris jadwal yang sudah menggumpal (laporan staf 25
     * Agu 2026: pinjaman 117-0151-01059, sisa tagihan menggumpal jadi satu
     * baris jasa Rp 36.300, jauh lebih besar dari angsuran normal harian).
     * Di sini: pinjaman 3 juta, 3 bulan, bunga 12%/tahun flat — normal per
     * bulan = pokok 1.000.000 + jasa 30.000, TERLEPAS dari baris jadwal
     * yang sengaja dibuat menggumpal (jasa Rp 500.000 dalam satu baris).
     */
    public function test_normal_installment_default_ignores_a_lumped_schedule_row(): void
    {
        $product = LoanProduct::factory()->create(['tenor_unit' => 'bulan', 'calculation_method' => 'flat']);
        $loan = Loan::factory()->create([
            'loan_product_id' => $product->id,
            'status' => 'dicairkan',
            'principal_amount' => 3000000,
            'tenor_unit' => 'bulan',
            'tenor_days' => 3,
            'interest_rate_percentage' => 12.0,
        ]);

        // Sisa tagihan "menggumpal" jadi satu baris — bukan 3 baris normal.
        LoanSchedule::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'due_date' => now(),
            'principal_amount' => 2000000,
            'interest_amount' => 500000,
            'total_amount' => 2500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        $normal = app(LoanRepaymentService::class)->normalInstallment($loan->fresh());

        $this->assertSame(1000000.0, $normal['principal']);
        $this->assertSame(30000.0, $normal['interest']);
    }

    /** Denda ikut diposting sebagai kredit ke akun Piutang Denda produk. */
    public function test_denda_is_posted_to_the_penalty_receivable_account_when_provided(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();
        $penaltyAccountId = $loan->loanProduct->coa_penalty_receivable_account_id;

        $response = $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'principal_portion' => 1000000,
            'interest_portion' => 100000,
            'penalty_portion' => 50000,
            'cash_account_id' => $this->cashAccountId(),
        ]);

        $response->assertRedirect(route('staf.angsuran.create'));
        $this->assertDatabaseHas('loan_repayments', [
            'loan_id' => $loan->id,
            'amount' => '1150000.00',
            'penalty_portion' => '50000.00',
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'chart_of_account_id' => $penaltyAccountId, 'credit' => '50000.00',
        ]);
    }

    /**
     * Laporan staf 26 Agu 2026: staf perlu lihat sisa pinjaman anggota
     * SEBELUM mengetik Nominal Bayar — field read-only di bawah selektor
     * Pinjaman, diisi JS dari peta outstandingBalances (lihat
     * LoanRepaymentService::outstandingPrincipal()). Dua baris jadwal di
     * sini (satu belum dibayar sama sekali, satu sudah dibayar sebagian)
     * membuktikan hitungannya sum(principal_amount)-sum(paid_principal_amount)
     * di seluruh baris, bukan cuma baris pertama.
     */
    public function test_create_view_exposes_the_selected_loan_outstanding_principal_balance(): void
    {
        $user = $this->petugasKredit();
        $loan = $this->disbursedLoanWithThreeInstallments();
        $loan->schedules()->where('installment_number', 1)->update([
            'paid_principal_amount' => 400000,
            'paid_interest_amount' => 100000,
            'paid_amount' => 500000,
            'status' => 'sebagian',
        ]);

        $response = $this->actingAs($user)->get(route('staf.angsuran.create'));

        $response->assertOk();
        // Sisa pokok = (1.000.000*3) - 400.000 = 2.600.000.
        $response->assertSee('"'.$loan->id.'":2600000', false);
    }

    public function test_denda_without_a_configured_penalty_account_is_rejected_with_a_friendly_error(): void
    {
        $user = $this->petugasKredit();
        $product = LoanProduct::factory()->create(['coa_penalty_receivable_account_id' => null]);
        $loan = Loan::factory()->create(['loan_product_id' => $product->id, 'status' => 'dicairkan', 'principal_amount' => 3000000]);
        LoanSchedule::query()->create([
            'loan_id' => $loan->id, 'installment_number' => 1, 'due_date' => now(),
            'principal_amount' => 1000000, 'interest_amount' => 100000, 'total_amount' => 1100000,
            'paid_amount' => 0, 'status' => 'belum_bayar',
        ]);

        $response = $this->actingAs($user)->post(route('staf.angsuran.store'), [
            'loan_id' => $loan->id,
            'principal_portion' => 0,
            'interest_portion' => 0,
            'penalty_portion' => 50000,
            'cash_account_id' => $this->cashAccountId(),
        ]);

        $response->assertSessionHasErrors('principal_portion');
        $this->assertDatabaseCount('loan_repayments', 0);
    }
}
