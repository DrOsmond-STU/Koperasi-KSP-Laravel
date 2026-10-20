<?php

namespace Tests\Feature\Retribution;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\RetributionTransaction;
use App\Models\RetributionType;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\JournalEngine;
use App\Services\Retribution\RetributionReportService;
use App\Services\Retribution\RetributionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Laporan Harian UPF harus bisa dicocokkan dengan Kartu Buku Besar kas pada
 * tanggal yang sama. Kasus nyata 13 Okt 2026: transaksi bertanggal 12 Okt
 * dibatalkan 13 Okt — jurnal pembaliknya bertanggal 13 Okt, sehingga laporan
 * 13 Okt selisih Rp 6.500 dari Buku Besar.
 */
class RekonsiliasiHarianUpfTest extends TestCase
{
    use RefreshDatabase;

    private User $petugas;

    private Branch $cabang;

    private RetributionType $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->petugas = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $this->petugas->assignRole('petugas_upf');
        UserBranchScope::query()->create(['user_id' => $this->petugas->id, 'scope_type' => 'all']);
        $this->cabang = Branch::factory()->create();
        $this->jenis = RetributionType::factory()->create([
            'percentage' => 100,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
    }

    private function catat(string $tanggal, float $nominal, string $nama): RetributionTransaction
    {
        return app(RetributionService::class)->record(
            branchId: $this->cabang->id,
            payerType: 'umum',
            payerName: $nama,
            member: null,
            totalAmount: $nominal,
            paymentMethod: 'tunai',
            createdBy: $this->petugas->id,
            date: Carbon::parse($tanggal),
            retributionType: $this->jenis,
        );
    }

    /** Mutasi kas retribusi di Buku Besar pada satu tanggal (yang dibaca Kartu Buku Besar). */
    private function mutasiKasBukuBesar(string $tanggal): float
    {
        return (float) DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.chart_of_account_id', $this->jenis->coa_revenue_account_id)
            ->whereDate('e.entry_date', $tanggal)
            ->selectRaw('COALESCE(SUM(l.credit) - SUM(l.debit), 0) n')
            ->value('n');
    }

    private function totalLaporan(string $tanggal): float
    {
        return (float) app(RetributionReportService::class)
            ->retribusiUpf(['branch_id' => $this->cabang->id, 'period_start' => $tanggal, 'period_end' => $tanggal])
            ->sum('total_amount');
    }

    public function test_cross_day_cancellations_are_listed_so_the_daily_report_matches_the_ledger(): void
    {
        // 9 Okt: transaksi B, dibatalkan 12 Okt.
        $this->travelTo(Carbon::parse('2026-10-09 10:00'));
        $b = $this->catat('2026-10-09', 6500, 'Pedagang B');
        $this->catat('2026-10-09', 100000, 'Aktif 9 Okt');

        // 12 Okt: transaksi aktif + pembatalan B.
        $this->travelTo(Carbon::parse('2026-10-12 14:00'));
        $this->catat('2026-10-12', 250000, 'Aktif 12 Okt');
        app(RetributionService::class)->reverseTransaction($b, 'SALAH', $this->petugas->id);

        // 13 Okt: transaksi A diinput dengan tanggal 12 Okt lalu dibatalkan 13 Okt
        // (persis RB-261013-6783), plus transaksi aktif 13 Okt.
        $this->travelTo(Carbon::parse('2026-10-13 12:18'));
        $a = $this->catat('2026-10-12', 6500, 'A.1');
        $this->catat('2026-10-13', 300000, 'Aktif 13 Okt');
        $this->travelTo(Carbon::parse('2026-10-13 12:20'));
        app(RetributionService::class)->reverseTransaction($a->fresh(), 'ada dua nama', $this->petugas->id);

        $svc = app(RetributionReportService::class);

        $rek13 = $svc->rekonsiliasiHarian($this->cabang->id, '2026-10-13');
        $this->assertCount(0, $rek13['masuk']);
        $this->assertSame([$a->transaction_number], $rek13['batal']->pluck('transaction_number')->all());
        $this->assertSame(300000.0, $this->totalLaporan('2026-10-13'));
        // Inilah selisih 6.500 yang dilaporkan — dan rekonsiliasinya menjelaskannya tepat.
        $this->assertEqualsWithDelta(293500, $this->mutasiKasBukuBesar('2026-10-13'), 0.001);
        $this->assertEqualsWithDelta($this->mutasiKasBukuBesar('2026-10-13'), $this->totalLaporan('2026-10-13') + $rek13['total_masuk'] - $rek13['total_batal'], 0.001);

        // 12 Okt: dua selisih 6.500 yang kebetulan saling menutupi — tetap dirinci.
        $rek12 = $svc->rekonsiliasiHarian($this->cabang->id, '2026-10-12');
        $this->assertSame([$a->transaction_number], $rek12['masuk']->pluck('transaction_number')->all());
        $this->assertSame([$b->transaction_number], $rek12['batal']->pluck('transaction_number')->all());
        $this->assertEqualsWithDelta($this->mutasiKasBukuBesar('2026-10-12'), $this->totalLaporan('2026-10-12') + $rek12['total_masuk'] - $rek12['total_batal'], 0.001);

        // 9 Okt: B masih tercatat di Buku Besar hari itu.
        $rek9 = $svc->rekonsiliasiHarian($this->cabang->id, '2026-10-09');
        $this->assertSame([$b->transaction_number], $rek9['masuk']->pluck('transaction_number')->all());
        $this->assertEqualsWithDelta($this->mutasiKasBukuBesar('2026-10-09'), $this->totalLaporan('2026-10-09') + $rek9['total_masuk'] - $rek9['total_batal'], 0.001);
    }

    public function test_extra_reversal_and_its_neutralising_journal_are_listed_as_corrections(): void
    {
        // Seperti #13163: transaksi 9 Okt dibatalkan resmi, tetapi sempat
        // terbentuk pembalik berlebih (klik ganda, sebelum ada pengaman) yang
        // lalu dinetralkan 16 Okt.
        $this->travelTo(Carbon::parse('2026-10-09 14:53'));
        $t = $this->catat('2026-10-09', 6500, 'spc.ON');
        $this->travelTo(Carbon::parse('2026-10-09 14:58'));
        app(RetributionService::class)->reverseTransaction($t, 'SALAH', $this->petugas->id);

        $asal = $t->journalEntry()->with('lines')->first();
        $berlebih = JournalEntry::query()->create([
            'branch_id' => $asal->branch_id, 'entry_date' => '2026-10-09', 'created_by' => $this->petugas->id,
            'description' => "Koreksi/pembalik jurnal #{$asal->id}: SALAH", 'reversal_of_entry_id' => $asal->id,
        ]);
        foreach ($asal->lines as $l) {
            $berlebih->lines()->create(['chart_of_account_id' => $l->chart_of_account_id, 'debit' => $l->credit, 'credit' => $l->debit]);
        }

        $this->travelTo(Carbon::parse('2026-10-16 15:18'));
        $penetral = app(JournalEngine::class)->reverse($berlebih->load('lines'), 'menetralkan', $this->petugas->id);

        $svc = app(RetributionReportService::class);
        $rek9 = $svc->rekonsiliasiHarian($this->cabang->id, '2026-10-09');
        $this->assertSame([$berlebih->id], $rek9['koreksi']->pluck('journal_id')->all());
        $this->assertEqualsWithDelta(-6500, $rek9['total_koreksi'], 0.001);
        $this->assertEqualsWithDelta($this->mutasiKasBukuBesar('2026-10-09'), $this->totalLaporan('2026-10-09') + $rek9['total_masuk'] - $rek9['total_batal'] + $rek9['total_koreksi'], 0.001);

        $rek16 = $svc->rekonsiliasiHarian($this->cabang->id, '2026-10-16');
        $this->assertSame([$penetral->id], $rek16['koreksi']->pluck('journal_id')->all());
        $this->assertEqualsWithDelta(6500, $rek16['total_koreksi'], 0.001);
        $this->assertEqualsWithDelta($this->mutasiKasBukuBesar('2026-10-16'), $this->totalLaporan('2026-10-16') + $rek16['total_masuk'] - $rek16['total_batal'] + $rek16['total_koreksi'], 0.001);
    }

    public function test_same_day_cancellation_needs_no_reconciliation_line(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 11:00'));
        $t = $this->catat('2026-10-14', 7500, 'Salah nama');
        app(RetributionService::class)->reverseTransaction($t, 'SALAH NAMA', $this->petugas->id);

        $rek = app(RetributionReportService::class)->rekonsiliasiHarian($this->cabang->id, '2026-10-14');
        $this->assertCount(0, $rek['masuk']);
        $this->assertCount(0, $rek['batal']);
    }

    public function test_daily_print_still_downloads_with_the_reconciliation_section(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 09:00'));
        $a = $this->catat('2026-10-12', 6500, 'A.1');
        $this->travelTo(Carbon::parse('2026-10-13 12:20'));
        app(RetributionService::class)->reverseTransaction($a, 'ada dua nama', $this->petugas->id);

        $response = $this->actingAs($this->petugas)->get(route('staf.retribusi-upf.print-harian', [
            'tanggal' => '2026-10-13', 'branch_id' => $this->cabang->id,
        ]));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }
}
