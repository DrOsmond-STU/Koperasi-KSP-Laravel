<?php

namespace Tests\Feature\Savings;

use App\Exceptions\Savings\TransactionAlreadyCancelledException;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Savings\SavingsService;
use App\Services\Savings\SavingsWithdrawalRequestService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md E2E-02: Teller setor simpanan → preview jurnal →
 * simpan → muncul di feed.
 */
class TellerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function teller(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_teller_can_preview_then_commit_a_deposit(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        $preview = $this->actingAs($teller)->post(route('staf.teller.preview'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 200000,
        ]);

        $preview->assertOk();
        $preview->assertSee('1101'); // kas account visible in the preview lines
        $preview->assertSee($account->savingsProduct->liabilityAccount->code);

        $store = $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 200000,
        ]);

        $store->assertRedirect(route('staf.teller.create'));
        $this->assertEquals(200000, $account->fresh()->balance);
        $this->assertDatabaseHas('savings_transactions', [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 200000,
        ]);
    }

    public function test_teller_feed_shows_todays_transaction(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 75000,
        ]);

        $response = $this->actingAs($teller)->get(route('staf.teller.create'));

        $response->assertOk();
        $response->assertSee($account->member->name);
    }

    /**
     * Laporan staf 26 Agu 2026: form Setor/Tarik sebelumnya tidak punya
     * field tanggal sama sekali (selalu now()) — banyak transaksi lama
     * yang belum sempat dicatat perlu bisa disusulkan dengan tanggal
     * aslinya. Field WAJIB diisi (tidak default hari ini).
     */
    public function test_transaction_date_is_required_and_persisted_on_both_the_transaction_and_its_journal_entry(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 90000,
        ])->assertSessionHasErrors('transaction_date');
        $this->assertDatabaseCount('savings_transactions', 0);

        $backdated = now()->subDays(10)->toDateString();
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => $backdated,
            'amount' => 90000,
        ])->assertRedirect(route('staf.teller.create'));

        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();
        $this->assertEquals($backdated, $tx->transaction_date->toDateString());
        $this->assertDatabaseHas('journal_entries', [
            'id' => $tx->journal_entry_id,
            'entry_date' => $backdated,
        ]);
    }

    public function test_future_transaction_date_is_rejected(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->addDay()->toDateString(),
            'amount' => 90000,
        ])->assertSessionHasErrors('transaction_date');

        $this->assertDatabaseCount('savings_transactions', 0);
    }

    /**
     * Regresi: dropdown Rekening di halaman Teller sebelumnya dibatasi
     * ->latest()->limit(50), sehingga dari ratusan rekening aktif produksi
     * hanya 50 yang paling baru dibuat yang muncul — mayoritas rekening
     * Simpanan Pokok/Wajib/Sukarela anggota lama hilang begitu saja.
     */
    public function test_teller_dropdown_lists_every_active_savings_account_not_just_the_50_newest(): void
    {
        $teller = $this->teller();
        $accounts = SavingsAccount::factory()->count(60)->create(['status' => 'aktif']);
        $oldestAccount = $accounts->first(); // ->latest() would have pushed this out of a limit(50) window

        $response = $this->actingAs($teller)->get(route('staf.teller.create'));

        $response->assertOk();
        $response->assertViewHas('accounts', fn ($listed) => $listed->count() >= 60);
        $response->assertSee($oldestAccount->account_number);
    }

    /**
     * Regresi: staf minta akun kas lawan transaksi bisa dilihat langsung
     * di halaman Teller & Buka Rekening (bukan cuma muncul di panel
     * "Preview Jurnal" setelah submit) supaya bisa diverifikasi dulu akun
     * nya benar atau tidak sebelum transaksi diproses.
     */
    public function test_teller_page_shows_the_cash_account_used_for_transactions(): void
    {
        $teller = $this->teller();

        $response = $this->actingAs($teller)->get(route('staf.teller.create'));

        $response->assertOk();
        $response->assertSee('1101');
        $response->assertSee('Kas');
    }

    public function test_buka_rekening_page_shows_the_cash_account_used_for_initial_deposit(): void
    {
        $teller = $this->teller();

        $response = $this->actingAs($teller)->get(route('staf.teller.buka-rekening.create'));

        $response->assertOk();
        $response->assertSee('1101');
        $response->assertSee('Kas');
    }

    public function test_anggota_role_cannot_access_teller_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('anggota');

        $this->actingAs($user)
            ->get(route('staf.teller.create'))
            ->assertForbidden();
    }

    public function test_creator_can_cancel_own_deposit_and_balance_reverts(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 150000,
        ]);
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $response = $this->actingAs($teller)->post(route('staf.teller.cancel', $tx), [
            'reason' => 'Salah input nominal',
        ]);

        $response->assertRedirect(route('staf.teller.create'));
        $this->assertEquals(0, $account->fresh()->balance);
        $tx->refresh();
        $this->assertNotNull($tx->cancelled_at);
        $this->assertEquals('Salah input nominal', $tx->cancellation_reason);
        $this->assertNotNull($tx->reversal_journal_entry_id);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $tx->reversal_journal_entry_id,
            'reversal_of_entry_id' => $tx->journal_entry_id,
        ]);
    }

    public function test_other_teller_cannot_cancel_someone_elses_transaction(): void
    {
        $creator = $this->teller();
        $otherTeller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        $this->actingAs($creator)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 100000,
        ]);
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($otherTeller)->post(route('staf.teller.cancel', $tx), [
            'reason' => 'Coba batalkan punya orang lain',
        ])->assertForbidden();

        $this->assertEquals(100000, $account->fresh()->balance);
        $this->assertNull($tx->fresh()->cancelled_at);
    }

    public function test_manajer_can_cancel_another_tellers_transaction(): void
    {
        $creator = $this->teller();
        $manajer = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $manajer->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $manajer->id, 'scope_type' => 'all']);

        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($creator)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 50000,
        ]);
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($manajer)->post(route('staf.teller.cancel', $tx), [
            'reason' => 'Koreksi oleh manajer',
        ])->assertRedirect(route('staf.teller.create'));

        $this->assertEquals(0, $account->fresh()->balance);
    }

    public function test_cancelling_an_already_cancelled_transaction_is_rejected(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 60000,
        ]);
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($teller)->post(route('staf.teller.cancel', $tx), ['reason' => 'Pertama']);

        $this->expectException(TransactionAlreadyCancelledException::class);
        app(SavingsService::class)->reverseTransaction($tx->fresh(), 'Kedua', $teller->id);
    }

    public function test_teller_can_approve_a_members_withdrawal_request(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $requester = User::factory()->create();
        $withdrawalRequest = app(SavingsWithdrawalRequestService::class)
            ->request($account, 100000, $requester->id);

        $response = $this->actingAs($teller)->post(route('staf.teller.decide-withdrawal', $withdrawalRequest), [
            'decision' => 'setuju',
        ]);

        $response->assertRedirect(route('staf.teller.create'));
        $this->assertEquals('disetujui', $withdrawalRequest->fresh()->status);
        $this->assertEquals(400000, (float) $account->fresh()->balance);
    }

    public function test_teller_can_reject_a_members_withdrawal_request(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $requester = User::factory()->create();
        $withdrawalRequest = app(SavingsWithdrawalRequestService::class)
            ->request($account, 100000, $requester->id);

        $response = $this->actingAs($teller)->post(route('staf.teller.decide-withdrawal', $withdrawalRequest), [
            'decision' => 'tolak',
            'notes' => 'Data anggota tidak sesuai',
        ]);

        $response->assertRedirect(route('staf.teller.create'));
        $this->assertEquals('ditolak', $withdrawalRequest->fresh()->status);
        $this->assertEquals(500000, (float) $account->fresh()->balance);
    }

    public function test_role_without_simpanan_approve_permission_cannot_decide_a_withdrawal(): void
    {
        $petugasKredit = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $petugasKredit->assignRole('petugas_kredit');
        UserBranchScope::query()->create(['user_id' => $petugasKredit->id, 'scope_type' => 'all']);

        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $requester = User::factory()->create();
        $withdrawalRequest = app(SavingsWithdrawalRequestService::class)
            ->request($account, 100000, $requester->id);

        $this->actingAs($petugasKredit)->post(route('staf.teller.decide-withdrawal', $withdrawalRequest), [
            'decision' => 'setuju',
        ])->assertForbidden();

        $this->assertEquals('menunggu', $withdrawalRequest->fresh()->status);
    }

    /**
     * Regresi untuk gap "anggota baru belum bisa setor pertama kali" —
     * dropdown Teller (create()) hanya memuat SavingsAccount yang sudah
     * ada, jadi anggota tanpa rekening sama sekali butuh jalur terpisah
     * ini (staf.teller.buka-rekening.*) untuk buka rekening + setoran
     * awal sekaligus.
     */
    public function test_teller_can_open_new_savings_account_with_initial_deposit_for_member_with_no_accounts_yet(): void
    {
        $teller = $this->teller();
        $member = Member::factory()->create(['status' => 'aktif']);
        $product = SavingsProduct::factory()->create(['category' => 'pokok', 'minimum_initial_deposit' => 25000]);

        $this->assertSame(0, SavingsAccount::query()->where('member_id', $member->id)->count());

        $response = $this->actingAs($teller)->post(route('staf.teller.buka-rekening.store'), [
            'member_id' => $member->id,
            'product_ids' => [$product->id],
            'initial_deposits' => [$product->id => 25000],
        ]);

        $response->assertRedirect(route('staf.teller.create'));

        $account = SavingsAccount::query()->where('member_id', $member->id)->where('savings_product_id', $product->id)->firstOrFail();
        $this->assertEquals('aktif', $account->status);
        $this->assertEquals(25000, (float) $account->balance);
        $this->assertDatabaseHas('savings_transactions', [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'amount' => 25000,
            'description' => 'Setoran awal pembukaan rekening',
        ]);
    }

    public function test_teller_can_open_multiple_savings_products_at_once(): void
    {
        $teller = $this->teller();
        $member = Member::factory()->create(['status' => 'aktif']);
        $pokok = SavingsProduct::factory()->create(['category' => 'pokok', 'minimum_initial_deposit' => 25000]);
        $wajib = SavingsProduct::factory()->create(['category' => 'wajib', 'minimum_initial_deposit' => 10000]);

        $this->actingAs($teller)->post(route('staf.teller.buka-rekening.store'), [
            'member_id' => $member->id,
            'product_ids' => [$pokok->id, $wajib->id],
            'initial_deposits' => [$pokok->id => 25000, $wajib->id => 10000],
        ])->assertRedirect(route('staf.teller.create'));

        $this->assertEquals(2, SavingsAccount::query()->where('member_id', $member->id)->count());
        $this->assertEquals(25000, (float) SavingsAccount::query()->where('member_id', $member->id)->where('savings_product_id', $pokok->id)->value('balance'));
        $this->assertEquals(10000, (float) SavingsAccount::query()->where('member_id', $member->id)->where('savings_product_id', $wajib->id)->value('balance'));
    }

    public function test_opening_account_below_minimum_initial_deposit_is_rejected(): void
    {
        $teller = $this->teller();
        $member = Member::factory()->create(['status' => 'aktif']);
        $product = SavingsProduct::factory()->create(['minimum_initial_deposit' => 50000]);

        $response = $this->actingAs($teller)->post(route('staf.teller.buka-rekening.store'), [
            'member_id' => $member->id,
            'product_ids' => [$product->id],
            'initial_deposits' => [$product->id => 10000],
        ]);

        $response->assertSessionHasErrors(["initial_deposits.{$product->id}"]);
        $this->assertSame(0, SavingsAccount::query()->where('member_id', $member->id)->count());
    }

    public function test_opening_account_for_a_product_the_member_already_has_active_is_skipped_not_duplicated(): void
    {
        $teller = $this->teller();
        $member = Member::factory()->create(['status' => 'aktif']);
        $product = SavingsProduct::factory()->create(['minimum_initial_deposit' => 0]);
        SavingsAccount::factory()->create(['member_id' => $member->id, 'savings_product_id' => $product->id, 'status' => 'aktif']);

        $response = $this->actingAs($teller)->post(route('staf.teller.buka-rekening.store'), [
            'member_id' => $member->id,
            'product_ids' => [$product->id],
            'initial_deposits' => [$product->id => 0],
        ]);

        $response->assertRedirect(route('staf.teller.buka-rekening.create'));
        $this->assertSame(1, SavingsAccount::query()->where('member_id', $member->id)->where('savings_product_id', $product->id)->count());
    }

    /**
     * Laporan staf 26 Agu 2026: panel "Transaksi Hari Ini" cuma menampilkan
     * hari ini — halaman Riwayat terpisah ini harus menampilkan transaksi
     * dari tanggal LAIN juga (bukti field Tanggal Transaksi benar-benar
     * dipakai, bukan cuma dekorasi).
     */
    public function test_history_page_lists_transactions_from_other_days_too(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $oldDate = now()->subDays(20)->toDateString();

        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => $oldDate,
            'amount' => 45000,
        ]);

        $response = $this->actingAs($teller)->get(route('staf.teller.history'));

        $response->assertOk();
        $response->assertSee($account->account_number);
        $response->assertSee($account->member->name);
    }

    public function test_history_page_search_filters_by_account_number(): void
    {
        $teller = $this->teller();
        $matching = SavingsAccount::factory()->create(['balance' => 0]);
        $other = SavingsAccount::factory()->create(['balance' => 0]);

        foreach ([$matching, $other] as $account) {
            $this->actingAs($teller)->post(route('staf.teller.store'), [
                'savings_account_id' => $account->id,
                'type' => 'setor',
                'transaction_date' => now()->toDateString(),
                'amount' => 30000,
            ]);
        }

        $response = $this->actingAs($teller)->get(route('staf.teller.history', ['q' => $matching->account_number]));

        $response->assertOk();
        $response->assertSee($matching->account_number);
        $response->assertDontSee($other->account_number);
    }

    public function test_history_page_filters_by_type_and_status(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 200000]);

        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 30000,
        ]);
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'tarik',
            'transaction_date' => now()->toDateString(),
            'amount' => 20000,
        ]);
        $withdrawal = SavingsTransaction::query()->where('type', 'tarik')->firstOrFail();
        $this->actingAs($teller)->post(route('staf.teller.cancel', $withdrawal), ['reason' => 'Salah catat']);

        $onlySetor = $this->actingAs($teller)->get(route('staf.teller.history', ['type' => 'setor']));
        $onlySetor->assertOk();
        $onlySetor->assertViewHas('transactions', fn ($page) => $page->total() === 1 && $page->first()->type === 'setor');

        $onlyCancelled = $this->actingAs($teller)->get(route('staf.teller.history', ['status' => 'dibatalkan']));
        $onlyCancelled->assertOk();
        $onlyCancelled->assertViewHas('transactions', fn ($page) => $page->total() === 1 && $page->first()->isCancelled());
    }

    public function test_history_page_can_be_cancelled_from_there_too(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->subDays(3)->toDateString(),
            'amount' => 40000,
        ]);
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($teller)->post(route('staf.teller.cancel', $tx), [
            'reason' => 'Salah input, batalkan dari riwayat',
        ])->assertRedirect(route('staf.teller.create'));

        $this->assertTrue($tx->fresh()->isCancelled());
    }

    /**
     * Laporan staf 26 Agu 2026: "tambahkan edit di riwayat transaksi".
     * Ledger append-only — edit() TIDAK mengubah baris asli, melainkan
     * membatalkannya (jurnal dibalik) lalu mencatat baris baru dengan nilai
     * terkoreksi. Di sini: setor 100.000 diedit jadi 150.000 pada tanggal
     * yang sama — saldo akhir harus 150.000 (bukan 250.000).
     */
    public function test_creator_can_edit_own_transaction_which_cancels_the_original_and_records_a_corrected_one(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->subDays(2)->toDateString(),
            'amount' => 100000,
        ]);
        $original = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();
        $newDate = now()->subDay()->toDateString();

        $response = $this->actingAs($teller)->put(route('staf.teller.update', $original), [
            'type' => 'setor',
            'transaction_date' => $newDate,
            'amount' => 150000,
            'description' => 'Koreksi nominal',
            'reason' => 'Salah ketik nominal',
        ]);

        $response->assertRedirect(route('staf.teller.history'));
        $this->assertEquals(150000, (float) $account->fresh()->balance);

        $original->refresh();
        $this->assertTrue($original->isCancelled());
        $this->assertEquals('Salah ketik nominal', $original->cancellation_reason);
        $this->assertNotNull($original->reversal_journal_entry_id);

        $corrected = SavingsTransaction::query()->where('savings_account_id', $account->id)->where('id', '!=', $original->id)->firstOrFail();
        $this->assertEquals('150000.00', $corrected->amount);
        $this->assertEquals($newDate, $corrected->transaction_date->toDateString());
        $this->assertEquals('Koreksi nominal', $corrected->description);
        $this->assertFalse($corrected->isCancelled());
    }

    /** Edit yang mengganti Jenis (Setor -> Tarik) harus menghitung ulang saldo dari arah yang benar. */
    public function test_editing_changes_type_from_setor_to_tarik_and_recomputes_balance(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 500000]);
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 100000,
        ]);
        // Saldo sekarang 600.000.
        $original = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($teller)->put(route('staf.teller.update', $original), [
            'type' => 'tarik',
            'transaction_date' => now()->toDateString(),
            'amount' => 100000,
            'reason' => 'Ternyata ini penarikan, bukan setoran',
        ])->assertRedirect(route('staf.teller.history'));

        // Batalkan setor 100rb (600rb -> 500rb) lalu tarik 100rb (500rb -> 400rb).
        $this->assertEquals(400000, (float) $account->fresh()->balance);
    }

    public function test_other_teller_cannot_edit_someone_elses_transaction(): void
    {
        $creator = $this->teller();
        $otherTeller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($creator)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 100000,
        ]);
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($otherTeller)->get(route('staf.teller.edit', $tx))->assertForbidden();
        $this->actingAs($otherTeller)->put(route('staf.teller.update', $tx), [
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 999999,
            'reason' => 'Coba edit punya orang lain',
        ])->assertForbidden();

        $this->assertFalse($tx->fresh()->isCancelled());
        $this->assertEquals(100000, (float) $account->fresh()->balance);
    }

    public function test_manajer_can_edit_another_tellers_transaction(): void
    {
        $creator = $this->teller();
        $manajer = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $manajer->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $manajer->id, 'scope_type' => 'all']);

        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($creator)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 50000,
        ]);
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($manajer)->put(route('staf.teller.update', $tx), [
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 75000,
            'reason' => 'Koreksi oleh manajer',
        ])->assertRedirect(route('staf.teller.history'));

        $this->assertEquals(75000, (float) $account->fresh()->balance);
    }

    public function test_cannot_edit_an_already_cancelled_transaction(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 60000,
        ]);
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();
        $this->actingAs($teller)->post(route('staf.teller.cancel', $tx), ['reason' => 'Batal duluan']);

        $this->actingAs($teller)->get(route('staf.teller.edit', $tx))->assertStatus(422);

        $response = $this->actingAs($teller)->put(route('staf.teller.update', $tx), [
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 60000,
            'reason' => 'Coba edit yang sudah batal',
        ]);
        $response->assertRedirect(route('staf.teller.history'));
        $response->assertSessionHas('error');
    }

    public function test_edit_requires_a_reason(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 60000,
        ]);
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $this->actingAs($teller)->put(route('staf.teller.update', $tx), [
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 60000,
        ])->assertSessionHasErrors('reason');

        $this->assertFalse($tx->fresh()->isCancelled());
    }

    /**
     * Atomisitas: kalau langkah kedua (mencatat baris baru) gagal karena
     * saldo tidak cukup, langkah pertama (membatalkan baris asli) HARUS
     * ikut batal juga — baris asli tetap utuh, bukan "sudah dibatalkan tapi
     * gagal digantikan".
     */
    public function test_edit_that_would_overdraw_the_account_is_rejected_and_the_original_stays_intact(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->toDateString(),
            'amount' => 50000,
        ]);
        // Saldo sekarang 50.000.
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $response = $this->actingAs($teller)->put(route('staf.teller.update', $tx), [
            'type' => 'tarik',
            'transaction_date' => now()->toDateString(),
            'amount' => 999999,
            'reason' => 'Coba ubah jadi tarikan besar',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertFalse($tx->fresh()->isCancelled());
        $this->assertEquals(50000, (float) $account->fresh()->balance);
        $this->assertSame(1, SavingsTransaction::query()->where('savings_account_id', $account->id)->count());
    }

    public function test_edit_form_is_prefilled_with_the_current_transaction_values(): void
    {
        $teller = $this->teller();
        $account = SavingsAccount::factory()->create(['balance' => 0]);
        $this->actingAs($teller)->post(route('staf.teller.store'), [
            'savings_account_id' => $account->id,
            'type' => 'setor',
            'transaction_date' => now()->subDays(5)->toDateString(),
            'amount' => 80000,
            'description' => 'Setoran rutin',
        ]);
        $tx = SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail();

        $response = $this->actingAs($teller)->get(route('staf.teller.edit', $tx));

        $response->assertOk();
        $response->assertSee('value="80000.00"', false);
        $response->assertSee('Setoran rutin', false);
        $response->assertSee($account->account_number);
    }
}
