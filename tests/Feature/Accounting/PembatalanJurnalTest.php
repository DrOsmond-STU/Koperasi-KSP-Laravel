<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\AdjustmentException;
use App\Exceptions\Accounting\JournalPostingException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\AdjustmentService;
use App\Services\Accounting\JournalEngine;
use App\Services\Savings\SavingsService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pembatalan transaksi harus membatalkan jurnalnya TEPAT SEKALI — Buku Besar
 * dan Neraca membaca journal_lines, jadi jurnal pembalik ganda (temuan audit
 * produksi 16 Okt 2026: #3780 dan #13163) membuat koreksinya dobel.
 */
class PembatalanJurnalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now(), 'password' => Hash::make('rahasia123')]);
        $user->assignRole($role);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function jurnalManual(User $user): JournalEntry
    {
        return app(JournalEngine::class)->post([
            'branch_id' => Branch::factory()->create()->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Setor kasbon',
            'created_by' => $user->id,
            'lines' => [
                ['chart_of_account_id' => ChartOfAccount::factory()->create()->id, 'debit' => 44950000, 'credit' => 0],
                ['chart_of_account_id' => ChartOfAccount::factory()->create()->id, 'debit' => 0, 'credit' => 44950000],
            ],
        ]);
    }

    private function setoran(User $teller, int $nominal): SavingsTransaction
    {
        $account = SavingsAccount::factory()->create(['balance' => 0]);

        return app(SavingsService::class)->deposit($account, $nominal, $teller->id, 'Setoran uji');
    }

    public function test_a_journal_can_only_be_reversed_once(): void
    {
        $user = $this->user('bendahara');
        $jurnal = $this->jurnalManual($user);

        app(JournalEngine::class)->reverse($jurnal, 'Pertama', $user->id);

        try {
            app(JournalEngine::class)->reverse($jurnal, 'Kedua (klik ganda)', $user->id);
            $this->fail('Pembalikan kedua seharusnya ditolak.');
        } catch (JournalPostingException $e) {
            $this->assertStringContainsString('sudah pernah dibatalkan', $e->getMessage());
        }

        $this->assertSame(1, JournalEntry::query()->where('reversal_of_entry_id', $jurnal->id)->count());
    }

    public function test_double_click_on_cancel_reverses_the_journal_and_balance_only_once(): void
    {
        $teller = $this->user('teller');
        $setoran = $this->setoran($teller, 100000);
        // Penarikan: cek saldo tidak menghalangi pembatalan kedua, jadi yang
        // diuji benar-benar pengaman di JournalEngine::reverse().
        $tx = app(SavingsService::class)->withdraw($setoran->savingsAccount->fresh(), 60000, $teller->id, 'Tarik uji');

        // Dua permintaan yang membaca baris transaksi SEBELUM salah satunya selesai.
        $basi = SavingsTransaction::query()->findOrFail($tx->id);
        app(SavingsService::class)->reverseTransaction($tx, 'Klik pertama', $teller->id);

        try {
            app(SavingsService::class)->reverseTransaction($basi, 'Klik kedua', $teller->id);
            $this->fail('Pembatalan kedua seharusnya ditolak.');
        } catch (JournalPostingException) {
        }

        $this->assertSame(1, JournalEntry::query()->where('reversal_of_entry_id', $tx->journal_entry_id)->count());
        // 100.000 - 60.000 + 60.000 (dibatalkan sekali), bukan +120.000.
        $this->assertEquals(100000, $tx->savingsAccount->fresh()->balance);
    }

    public function test_cancel_page_shows_a_message_instead_of_an_error_page_when_the_journal_is_already_reversed(): void
    {
        $teller = $this->user('teller');
        $tx = $this->setoran($teller, 75000);
        app(JournalEngine::class)->reverse($tx->journalEntry, 'Dibalik di tempat lain', $teller->id);

        $this->actingAs($teller)->from(route('staf.teller.history'))
            ->post(route('staf.teller.cancel', $tx), ['reason' => 'Salah input'])
            ->assertRedirect(route('staf.teller.history'))
            ->assertSessionHas('error');

        $this->assertNull($tx->fresh()->cancelled_at);
        $this->assertEquals(75000, $tx->savingsAccount->fresh()->balance);
    }

    public function test_jurnal_penyesuaian_refuses_journals_owned_by_a_module_with_its_own_cancel(): void
    {
        $teller = $this->user('teller');
        $bendahara = $this->user('bendahara');
        $tx = $this->setoran($teller, 50000);

        $this->assertNotNull(app(AdjustmentService::class)->modulPemilik($tx->journalEntry));

        $this->expectException(AdjustmentException::class);
        app(AdjustmentService::class)->reverse($tx->journalEntry, 'Coba lewat penyesuaian', $bendahara, 'rahasia123');
    }

    public function test_an_extra_reversal_can_be_undone_through_jurnal_penyesuaian(): void
    {
        $bendahara = $this->user('bendahara');
        $jurnal = $this->jurnalManual($bendahara);
        $pembalik = app(JournalEngine::class)->reverse($jurnal, 'Batal', $bendahara->id);

        // Jurnal pembalik yang tidak tercatat di transaksi mana pun (seperti
        // #3782 / #13175 di produksi) boleh dibalik untuk menetralkannya.
        $this->assertNull(app(AdjustmentService::class)->modulPemilik($pembalik));
        $koreksi = app(AdjustmentService::class)->reverse($pembalik, 'Pembalik ganda', $bendahara, 'rahasia123');
        $this->assertSame($pembalik->id, $koreksi->reversal_of_entry_id);

        $this->actingAs($bendahara)->get(route('admin.jurnal-penyesuaian.create', ['entry_id' => $jurnal->id]))
            ->assertOk()->assertSee('sudah dibalik oleh jurnal')->assertDontSee('Posting Jurnal Balik');
    }

    public function test_imported_repayment_without_journal_cannot_be_cancelled(): void
    {
        $manajer = $this->user('manajer');
        $loan = Loan::factory()->create(['status' => 'dicairkan']);
        $angsuran = LoanRepayment::query()->create([
            'branch_id' => $loan->branch_id, 'loan_id' => $loan->id, 'amount' => 110000,
            'principal_portion' => 100000, 'interest_portion' => 10000, 'penalty_portion' => 0,
            'balance_after' => 0, 'paid_at' => '2026-05-10', 'migrated_at' => now(), 'created_by' => $manajer->id,
        ]);

        $this->actingAs($manajer)->from(route('staf.angsuran.daftar'))
            ->post(route('staf.angsuran.cancel', $angsuran), ['reason' => 'Coba'])
            ->assertSessionHas('error');

        $this->assertNull($angsuran->fresh()->cancelled_at);
        $this->actingAs($manajer)->get(route('staf.angsuran.daftar'))->assertDontSee('cancel-form-'.$angsuran->id, false);
    }
}
