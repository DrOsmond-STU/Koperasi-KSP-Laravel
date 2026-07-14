<?php

namespace Tests\Feature\Dashboard;

use App\Models\Branch;
use App\Models\CashCategory;
use App\Models\User;
use App\Services\Cash\TellerCashService;
use App\Services\Dashboard\CashBankDashboardService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers Task 2.6: Dashboard Kas/Bank breakdown per Kategori Kas Teller.
 */
class CashBankCategoryBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_breakdown_groups_todays_transactions_by_category(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $sewa = CashCategory::factory()->create(['name' => 'Pendapatan Sewa']);
        $listrik = CashCategory::factory()->keluar()->create(['name' => 'Beban Listrik Kantor']);

        $service = app(TellerCashService::class);
        $service->record($sewa, 200000, $branch->id, $user->id);
        $service->record($sewa, 100000, $branch->id, $user->id);
        $service->record($listrik, 50000, $branch->id, $user->id);

        $summary = app(CashBankDashboardService::class)->summary($branch->id);
        $breakdown = collect($summary['category_breakdown'])->keyBy('category_name');

        $this->assertEquals(300000, $breakdown['Pendapatan Sewa']['total']);
        $this->assertEquals('masuk', $breakdown['Pendapatan Sewa']['type']);
        $this->assertEquals(50000, $breakdown['Beban Listrik Kantor']['total']);
        $this->assertEquals('keluar', $breakdown['Beban Listrik Kantor']['type']);
    }
}
