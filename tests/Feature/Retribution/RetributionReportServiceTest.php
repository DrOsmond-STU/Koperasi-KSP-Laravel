<?php

namespace Tests\Feature\Retribution;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Member;
use App\Models\RetributionType;
use App\Models\User;
use App\Services\Reporting\ReportBuilderService;
use App\Services\Retribution\RetributionReportService;
use App\Services\Retribution\RetributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetributionReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?RetributionType $keb = null;

    private ?RetributionType $kam = null;

    private function recordSampleTransaction(): void
    {
        ChartOfAccount::factory()->create(['code' => '1101']);
        $this->keb = RetributionType::factory()->create([
            'name' => 'Retribusi Kebersihan',
            'percentage' => 60,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        $this->kam = RetributionType::factory()->create([
            'name' => 'Retribusi Keamanan',
            'percentage' => 40,
            'coa_revenue_account_id' => ChartOfAccount::factory()->create()->id,
            'is_active' => true,
        ]);
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        // Split types (< 100%) hanya dipakai untuk transaksi Anggota;
        // sample-nya pakai flow anggota supaya kedua jenis retribusi
        // dijurnal via pembagian otomatis (RetributionSplitCalculator).
        $member = Member::factory()->create(['name' => 'Ibu Sari']);

        app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'anggota',
            payerName: null,
            member: $member,
            totalAmount: 100000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );
    }

    public function test_retribusi_upf_report_reflects_transaction_lines_with_dynamic_columns(): void
    {
        $this->recordSampleTransaction();

        $rows = app(RetributionReportService::class)->retribusiUpf([
            'branch_id' => null,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ]);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertEquals('Ibu Sari', $row['payer_name']);
        $this->assertEquals(100000.0, $row['total_amount']);
        $this->assertEquals(60000.0, $row["retribusi_line_{$this->keb->id}"]);
        $this->assertEquals(40000.0, $row["retribusi_line_{$this->kam->id}"]);
    }

    public function test_generate_via_report_builder_service_accepts_static_columns(): void
    {
        $this->recordSampleTransaction();

        $rows = app(ReportBuilderService::class)->generate('retribusi_upf', ['transaction_date', 'payer_name', 'total_amount'], [
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ]);

        $this->assertCount(1, $rows);
        $this->assertEqualsCanonicalizing(['transaction_date', 'payer_name', 'total_amount'], array_keys($rows->first()));
    }

    public function test_generate_via_report_builder_service_rejects_dynamic_line_columns(): void
    {
        // ReportTypeRegistry hanya mendaftarkan kolom statis untuk retribusi_upf
        // (lihat komentar di ReportTypeRegistry) — kolom dinamis retribusi_line_{id}
        // HANYA divalidasi lewat GenerateRetributionReportRequest pada tab
        // Laporan modul UPF, bukan lewat ReportBuilderService::generate() umum.
        $this->recordSampleTransaction();

        $this->expectException(\App\Exceptions\Reporting\ReportBuilderException::class);

        app(ReportBuilderService::class)->generate('retribusi_upf', ["retribusi_line_{$this->keb->id}"], [
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ]);
    }

    public function test_rekap_pendapatan_totals_cash_and_breaks_down_by_jenis(): void
    {
        $this->recordSampleTransaction();

        $rekap = app(RetributionReportService::class)->rekapPendapatan([
            'branch_id' => null,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ]);

        $this->assertEquals(1, $rekap['transaction_count']);
        $this->assertEquals(1, $rekap['transaction_count_anggota']);
        $this->assertEquals(0, $rekap['transaction_count_umum']);
        $this->assertEquals(100000.0, $rekap['total_cash_in']);
        $this->assertCount(2, $rekap['breakdown']);
        // Highest total (60% split) berada di atas.
        $this->assertEquals('Retribusi Kebersihan', $rekap['breakdown'][0]['name']);
        $this->assertEquals(60000.0, $rekap['breakdown'][0]['total']);
        $this->assertEquals(40000.0, $rekap['breakdown'][1]['total']);
        // Σbreakdown === total_cash_in (invariant journal-lawan)
        $this->assertEquals(
            $rekap['total_cash_in'],
            array_sum(array_column($rekap['breakdown'], 'total')),
        );
    }

    public function test_rekap_pendapatan_excludes_cancelled_transactions(): void
    {
        $this->recordSampleTransaction();

        // Batalkan transaksi terakhir — rekap seharusnya turun ke nol.
        $tx = \App\Models\RetributionTransaction::query()->latest('id')->firstOrFail();
        app(RetributionService::class)->reverseTransaction($tx, 'Salah input', User::factory()->create()->id);

        $rekap = app(RetributionReportService::class)->rekapPendapatan([
            'branch_id' => null,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ]);

        $this->assertEquals(0, $rekap['transaction_count']);
        $this->assertEquals(0.0, $rekap['total_cash_in']);
        $this->assertSame([], $rekap['breakdown']);
    }
}
