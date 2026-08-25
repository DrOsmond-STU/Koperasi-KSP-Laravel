<?php

namespace Tests\Feature\Retribution;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\RetributionType;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetributionControllerTest extends TestCase
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

    private function activateFullyAllocatedType(): RetributionType
    {
        return RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
    }

    public function test_full_transaction_flow_petugas_upf_can_record_and_see_it_in_dashboard(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $type = $this->activateFullyAllocatedType();

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'retribution_type_id' => $type->id,
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));
        $this->assertDatabaseHas('retribution_transactions', ['payer_name' => 'Ibu Sari', 'total_amount' => 75000]);

        $index = $this->actingAs($officer)->get(route('staf.retribusi-upf.index'));
        $index->assertOk();
        $index->assertSee('Ibu Sari');
    }

    /**
     * Modul Retribusi (UPF) secara konsep terikat ke cabang "Unit
     * Pengelola Fasilitas (UPF)" — untuk user scope semua cabang, form
     * Transaksi & KPI dashboard harus default ke cabang itu, bukan
     * konsolidasi seluruh cabang (lihat RetributionController::
     * resolveBranchId()).
     */
    public function test_transaction_form_defaults_branch_to_upf_for_unrestricted_user(): void
    {
        $upf = Branch::factory()->create(['name' => 'Unit Pengelola Fasilitas ( UPF )']);
        Branch::factory()->create(['name' => 'Cabang Lain']);
        $officer = $this->petugasUpf();

        $response = $this->actingAs($officer)->get(route('staf.retribusi-upf.index'));

        $response->assertOk();
        $response->assertSee('<option value="'.$upf->id.'" selected>', false);
    }

    /**
     * User dengan scope terbatas yang TIDAK punya akses ke cabang UPF
     * tetap fallback ke cabang pertama yang diizinkan — tidak boleh
     * default ke cabang yang bukan haknya.
     */
    public function test_transaction_form_falls_back_to_first_allowed_branch_when_upf_not_allowed(): void
    {
        Branch::factory()->create(['name' => 'Unit Pengelola Fasilitas ( UPF )']);
        $ownBranch = Branch::factory()->create(['name' => 'Cabang Sendiri']);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'single', 'single_branch_id' => $ownBranch->id]);

        $response = $this->actingAs($user)->get(route('staf.retribusi-upf.index'));

        $response->assertOk();
        $response->assertSee('<option value="'.$ownBranch->id.'" selected>', false);
    }

    public function test_role_without_permission_cannot_access_retribusi_upf(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');

        $this->actingAs($user)->get(route('staf.retribusi-upf.index'))->assertForbidden();
    }

    public function test_index_is_gated_not_just_store(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');

        $this->actingAs($user)->get(route('staf.retribusi-upf.index'))->assertForbidden();
        $this->actingAs($user)->post(route('staf.retribusi-upf.store'), [])->assertForbidden();
    }

    public function test_branch_scope_mismatch_is_rejected(): void
    {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_upf');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'single', 'single_branch_id' => $ownBranch->id]);
        $type = $this->activateFullyAllocatedType();

        $response = $this->actingAs($user)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $otherBranch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'retribution_type_id' => $type->id,
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('retribution_transactions', 0);
    }

    /**
     * "Persentase belum 100%" sekarang cuma relevan untuk mode ANGGOTA
     * (split otomatis ke seluruh jenis "split") — mode UMUM tidak lagi
     * di-split, ia mewajibkan satu jenis 100% dipilih eksplisit (lihat
     * RetributionService::assertUmumType()).
     */
    public function test_store_shows_domain_error_when_split_percentages_not_fully_allocated(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $member = \App\Models\Member::factory()->create();
        RetributionType::factory()->create([
            'percentage' => 50,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'anggota',
            'member_id' => $member->id,
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        $response->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('retribution_transactions', 0);
    }

    private function recordTransaction(User $officer, Branch $branch): \App\Models\RetributionTransaction
    {
        $type = $this->activateFullyAllocatedType();

        $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => 'Ibu Sari',
            'retribution_type_id' => $type->id,
            'total_amount' => 75000,
            'payment_method' => 'tunai',
        ]);

        return \App\Models\RetributionTransaction::query()->where('payer_name', 'Ibu Sari')->firstOrFail();
    }

    public function test_creator_can_cancel_own_retribution_transaction(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $trx = $this->recordTransaction($officer, $branch);

        $response = $this->actingAs($officer)->post(route('staf.retribusi-upf.cancel', $trx), [
            'reason' => 'Salah catat pembayar',
        ]);

        $response->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));
        $trx->refresh();
        $this->assertNotNull($trx->cancelled_at);
        $this->assertNotNull($trx->reversal_journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $trx->reversal_journal_entry_id,
            'reversal_of_entry_id' => $trx->journal_entry_id,
        ]);
    }

    public function test_other_officer_cannot_cancel_someone_elses_retribution_transaction(): void
    {
        $creator = $this->petugasUpf();
        $otherOfficer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $trx = $this->recordTransaction($creator, $branch);

        $this->actingAs($otherOfficer)->post(route('staf.retribusi-upf.cancel', $trx), [
            'reason' => 'Coba batalkan punya orang lain',
        ])->assertForbidden();

        $this->assertNull($trx->fresh()->cancelled_at);
    }

    public function test_manajer_can_cancel_any_retribution_transaction(): void
    {
        $creator = $this->petugasUpf();
        $manajer = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $manajer->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $manajer->id, 'scope_type' => 'all']);
        $branch = Branch::factory()->create();
        $trx = $this->recordTransaction($creator, $branch);

        $this->actingAs($manajer)->post(route('staf.retribusi-upf.cancel', $trx), [
            'reason' => 'Koreksi oleh manajer',
        ])->assertRedirect(route('staf.retribusi-upf.index', ['tab' => 'transaksi']));

        $this->assertNotNull($trx->fresh()->cancelled_at);
    }

    /**
     * Regresi: nama akun kas lawan (panel Preview Jurnal & JS live preview)
     * dulu tertulis statis "KAS AO RIDWAN (UPF)" di view — begitu admin
     * mengganti nama akun 1101600 lewat Master Bagan Akun, halaman
     * Transaksi harus ikut menampilkan nama barunya, bukan teks lama.
     */
    public function test_transaction_page_shows_live_cash_account_name_not_hardcoded(): void
    {
        // ChartOfAccountsSeeder (di setUp()) tidak membuat baris ini — yang
        // menyisipkannya adalah migration add_kas_ao_ridwan_upf_to_chart_of_accounts,
        // jadi di sini kita UPDATE nama akunnya (bukan create baru).
        \App\Models\ChartOfAccount::query()->updateOrCreate(
            ['code' => '1101600'],
            ['name' => 'KAS AO JAYA BUDIMAN (UPF)', 'type' => 'ASET', 'normal_balance' => 'DEBIT', 'is_postable' => true, 'statement' => 'NERACA'],
        );
        $officer = $this->petugasUpf();

        $response = $this->actingAs($officer)->get(route('staf.retribusi-upf.index'));

        $response->assertOk();
        $response->assertSee('KAS AO JAYA BUDIMAN (UPF)');
        $response->assertDontSee('KAS AO RIDWAN');
    }

    private function recordTransactionAs(User $officer, Branch $branch, RetributionType $type, string $payerName, string $date, float $amount = 75000, string $paymentMethod = 'tunai'): void
    {
        $this->actingAs($officer)->post(route('staf.retribusi-upf.store'), [
            'branch_id' => $branch->id,
            'payer_type' => 'umum',
            'payer_name' => $payerName,
            'retribution_type_id' => $type->id,
            'total_amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_date' => $date,
        ]);
    }

    /**
     * Regresi: tab Transaksi dulu cuma `limit(20)` tanpa pagination — kalau
     * satu cabang punya ≥20 transaksi di satu tanggal saja, tanggal-tanggal
     * sebelumnya jadi tidak pernah terlihat sama sekali. Sekarang seluruh
     * riwayat bisa dijangkau lewat halaman berikutnya.
     */
    public function test_transaction_list_paginates_and_older_transactions_are_reachable_via_page_2(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $type = $this->activateFullyAllocatedType();

        for ($i = 0; $i < 30; $i++) {
            $this->recordTransactionAs($officer, $branch, $type, "Pembayar Ke-{$i}", now()->subDays($i)->toDateString());
        }

        $page1 = $this->actingAs($officer)->get(route('staf.retribusi-upf.index', [
            'tab' => 'transaksi', 'branch_id' => $branch->id,
        ]));
        $page1->assertOk();
        $page1->assertSee('Pembayar Ke-0'); // paling baru -> halaman 1
        $page1->assertDontSee('Pembayar Ke-29'); // paling lama -> belum tampil

        $page2 = $this->actingAs($officer)->get(route('staf.retribusi-upf.index', [
            'tab' => 'transaksi', 'branch_id' => $branch->id, 'page' => 2,
        ]));
        $page2->assertOk();
        $page2->assertSee('Pembayar Ke-29');
    }

    public function test_transaction_list_can_be_searched_by_payer_name(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $type = $this->activateFullyAllocatedType();
        $this->recordTransactionAs($officer, $branch, $type, 'Toko Sinar Jaya', now()->toDateString());
        // Ditanggali kemarin (bukan hari ini) supaya tidak ikut nongol di
        // widget KPI Dashboard "Pembayar Hari Ini" — itu daftar TERPISAH
        // dari tabel Transaksi yang sedang diuji di sini, dan tidak
        // dibatasi oleh filter pencarian ini sama sekali.
        $this->recordTransactionAs($officer, $branch, $type, 'Warung Bu Tuti', now()->subDay()->toDateString());

        $response = $this->actingAs($officer)->get(route('staf.retribusi-upf.index', [
            'tab' => 'transaksi', 'branch_id' => $branch->id, 'q' => 'Sinar Jaya',
        ]));

        $response->assertOk();
        $response->assertSee('Toko Sinar Jaya');
        $response->assertDontSee('Warung Bu Tuti');
    }

    public function test_transaction_list_can_be_filtered_by_date_range(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $type = $this->activateFullyAllocatedType();
        $this->recordTransactionAs($officer, $branch, $type, 'Transaksi Lama', now()->subDays(10)->toDateString());
        $this->recordTransactionAs($officer, $branch, $type, 'Transaksi Baru', now()->toDateString());

        $response = $this->actingAs($officer)->get(route('staf.retribusi-upf.index', [
            'tab' => 'transaksi', 'branch_id' => $branch->id,
            'date_from' => now()->subDays(2)->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Transaksi Baru');
        $response->assertDontSee('Transaksi Lama');
    }

    public function test_transaction_list_can_be_filtered_by_cancelled_status(): void
    {
        $officer = $this->petugasUpf();
        $branch = Branch::factory()->create();
        $type = $this->activateFullyAllocatedType();
        // "Transaksi Aktif" ditanggali kemarin supaya tidak ikut nongol di
        // widget KPI Dashboard "Pembayar Hari Ini" (daftar terpisah dari
        // tabel Transaksi yang diuji di sini, lihat catatan yang sama di
        // test_transaction_list_can_be_searched_by_payer_name di atas).
        $this->recordTransactionAs($officer, $branch, $type, 'Transaksi Aktif', now()->subDay()->toDateString());
        $this->recordTransactionAs($officer, $branch, $type, 'Transaksi Batal', now()->toDateString());
        $toCancel = \App\Models\RetributionTransaction::query()->where('payer_name', 'Transaksi Batal')->firstOrFail();
        $this->actingAs($officer)->post(route('staf.retribusi-upf.cancel', $toCancel), ['reason' => 'Salah input']);

        $response = $this->actingAs($officer)->get(route('staf.retribusi-upf.index', [
            'tab' => 'transaksi', 'branch_id' => $branch->id, 'status' => 'dibatalkan',
        ]));

        $response->assertOk();
        $response->assertSee('Transaksi Batal');
        $response->assertDontSee('Transaksi Aktif');
    }
}
