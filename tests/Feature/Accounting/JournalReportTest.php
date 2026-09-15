<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\JournalEngine;
use App\Services\Accounting\JournalReportService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laporan Jurnal Transaksi — daftar entri jurnal beserta baris debet/kredit
 * lengkapnya, lintas semua sumber transaksi.
 *
 * Yang dijaga di sini bukan cuma "halamannya jalan", tapi sifat yang
 * membuat laporan ini ada: jurnal yang lahir dari transaksi (pencairan
 * pinjaman, angsuran) HARUS ikut terlihat. Daftar di layar Jurnal Umum
 * sengaja `whereNull('source_type')` — kalau laporan ini sampai ikut
 * menyaring begitu, ia kehilangan seluruh alasan keberadaannya tanpa ada
 * yang gagal secara mencolok.
 */
class JournalReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function bendahara(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    /** Posting satu entri seimbang; $source mensimulasikan jurnal yang lahir dari transaksi. */
    private function postingJurnal(string $description, string $date, ?Loan $source = null, float $amount = 100000): JournalEntry
    {
        $debit = ChartOfAccount::factory()->create(['normal_balance' => 'DEBIT', 'is_postable' => true]);
        $credit = ChartOfAccount::factory()->create(['normal_balance' => 'KREDIT', 'is_postable' => true]);

        return app(JournalEngine::class)->post(array_filter([
            'branch_id' => Branch::query()->firstOr(fn () => Branch::factory()->create())->id,
            'entry_date' => $date,
            'description' => $description,
            'created_by' => User::factory()->create()->id,
            'source' => $source,
            'lines' => [
                ['chart_of_account_id' => $debit->id, 'debit' => $amount, 'credit' => 0],
                ['chart_of_account_id' => $credit->id, 'debit' => 0, 'credit' => $amount],
            ],
        ]));
    }

    public function test_the_page_lists_entries_with_their_debit_and_credit_lines(): void
    {
        $entry = $this->postingJurnal('Pencairan pinjaman 51-260806-9353', '2026-08-06');
        $akunDebet = $entry->lines->firstWhere('debit', '>', 0)->account;

        $this->actingAs($this->bendahara())
            ->get(route('admin.jurnal-transaksi.index', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Pencairan pinjaman 51-260806-9353')
            ->assertSee($akunDebet->code)
            ->assertSee($akunDebet->name);
    }

    /** Inti laporan ini: jurnal bersumber transaksi tidak boleh tersembunyi. */
    public function test_entries_generated_by_a_transaction_are_included(): void
    {
        $loan = Loan::factory()->create();
        $this->postingJurnal('Pencairan pinjaman otomatis', '2026-08-06', $loan);

        $this->actingAs($this->bendahara())
            ->get(route('admin.jurnal-transaksi.index', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Pencairan pinjaman otomatis')
            ->assertSee('Pencairan Pinjaman');
    }

    public function test_the_period_filter_excludes_entries_outside_the_range(): void
    {
        $this->postingJurnal('Di dalam rentang', '2026-08-15');
        $this->postingJurnal('Di luar rentang', '2026-07-15');

        $this->actingAs($this->bendahara())
            ->get(route('admin.jurnal-transaksi.index', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Di dalam rentang')
            ->assertDontSee('Di luar rentang');
    }

    /** Menyaring per akun tetap menampilkan lawan debet/kreditnya — itu yang dicari pengguna. */
    public function test_filtering_by_account_still_shows_the_whole_entry(): void
    {
        $entry = $this->postingJurnal('Transaksi lengkap', '2026-08-10');
        $barisDebet = $entry->lines->firstWhere('debit', '>', 0);
        $barisKredit = $entry->lines->firstWhere('credit', '>', 0);

        $this->actingAs($this->bendahara())
            ->get(route('admin.jurnal-transaksi.index', [
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-31',
                'chart_of_account_id' => $barisDebet->chart_of_account_id,
            ]))
            ->assertOk()
            ->assertSee($barisKredit->account->code);
    }

    public function test_totals_cover_the_whole_filter_and_report_balance(): void
    {
        $this->postingJurnal('Satu', '2026-08-05', null, 100000);
        $this->postingJurnal('Dua', '2026-08-06', null, 250000);

        $totals = app(JournalReportService::class)->totals([
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]);

        $this->assertSame(2, $totals['entries']);
        $this->assertSame(4, $totals['lines']);
        $this->assertEqualsWithDelta(350000, (float) $totals['debit'], 0.01);
        $this->assertEqualsWithDelta(350000, (float) $totals['credit'], 0.01);
        $this->assertTrue($totals['balanced']);
    }

    public function test_source_labels_are_written_in_indonesian_and_never_blank(): void
    {
        $service = app(JournalReportService::class);

        $this->assertSame('Jurnal Umum (manual)', $service->labelSumber(null));
        $this->assertSame('Pencairan Pinjaman', $service->labelSumber(Loan::class));
        $this->assertSame('Angsuran Pinjaman', $service->labelSumber(LoanRepayment::class));
        // Sumber yang belum dikenal tetap terbaca, bukan kolom kosong.
        $this->assertSame('WidgetAneh', $service->labelSumber('App\\Models\\WidgetAneh'));
    }

    public function test_a_user_without_jurnal_read_is_rejected(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('petugas_kredit');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.jurnal-transaksi.index'))->assertForbidden();
    }

    public function test_the_print_route_returns_a_pdf(): void
    {
        $this->postingJurnal('Untuk dicetak', '2026-08-12');

        $response = $this->actingAs($this->bendahara())->get(route('admin.jurnal-transaksi.print', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
