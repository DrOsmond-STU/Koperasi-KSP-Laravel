<?php

namespace Tests\Feature\Retribution;

use App\Models\Branch;
use App\Models\ChartOfAccount;
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

        app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Ibu Sari',
            member: null,
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

    public function test_retribusi_upf_report_excludes_cancelled_transactions(): void
    {
        // Transaksi yang dibatalkan sudah tercermin di jurnal pembalik dan
        // tidak lagi jadi pendapatan — kalau masih ikut terhitung di sini,
        // nilainya selisih dengan Rekap Pendapatan UPF (yang sudah
        // mengecualikan cancelled_at sejak awal).
        $this->recordSampleTransaction();

        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $cancelled = app(RetributionService::class)->record(
            branchId: $branch->id,
            payerType: 'umum',
            payerName: 'Pak Budi',
            member: null,
            totalAmount: 50000,
            paymentMethod: 'tunai',
            createdBy: $user->id,
        );
        app(RetributionService::class)->reverseTransaction($cancelled, 'salah input', $user->id);

        $rows = app(RetributionReportService::class)->retribusiUpf([
            'branch_id' => null,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ]);

        $this->assertCount(1, $rows);
        $this->assertEquals('Ibu Sari', $rows->first()['payer_name']);
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
}
