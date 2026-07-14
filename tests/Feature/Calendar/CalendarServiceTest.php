<?php

namespace Tests\Feature\Calendar;

use App\Models\Branch;
use App\Models\CooperativeEvent;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Services\Calendar\CalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_view_includes_unpaid_loan_due_dates_and_excludes_paid_ones(): void
    {
        $branch = Branch::factory()->create();
        $loan = Loan::factory()->create(['branch_id' => $branch->id, 'status' => 'dicairkan']);

        $dueDate = Carbon::create(2026, 8, 15);

        LoanSchedule::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'due_date' => $dueDate,
            'principal_amount' => 400000,
            'interest_amount' => 50000,
            'total_amount' => 450000,
            'status' => 'belum_bayar',
        ]);

        LoanSchedule::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 2,
            'due_date' => $dueDate->copy()->addMonth(),
            'principal_amount' => 400000,
            'interest_amount' => 50000,
            'total_amount' => 450000,
            'status' => 'lunas',
        ]);

        $view = app(CalendarService::class)->monthView(2026, 8, $branch->id);

        $this->assertArrayHasKey('2026-08-15', $view);
        $this->assertEquals('jatuh_tempo_angsuran', $view['2026-08-15'][0]['type']);
    }

    public function test_month_view_includes_cooperative_events_filtered_by_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $eventForBranchA = CooperativeEvent::factory()->create([
            'start_at' => Carbon::create(2026, 8, 10),
            'end_at' => Carbon::create(2026, 8, 10, 12),
            'branch_scope_type' => 'single',
            'single_branch_id' => $branchA->id,
        ]);

        CooperativeEvent::factory()->create([
            'start_at' => Carbon::create(2026, 8, 12),
            'end_at' => Carbon::create(2026, 8, 12, 12),
            'branch_scope_type' => 'single',
            'single_branch_id' => $branchB->id,
        ]);

        $view = app(CalendarService::class)->monthView(2026, 8, $branchA->id);

        $this->assertArrayHasKey('2026-08-10', $view);
        $this->assertArrayNotHasKey('2026-08-12', $view);
    }

    public function test_all_branch_event_appears_regardless_of_selected_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        CooperativeEvent::factory()->create([
            'start_at' => Carbon::create(2026, 8, 20),
            'end_at' => Carbon::create(2026, 8, 20, 12),
            'branch_scope_type' => 'all',
        ]);

        $view = app(CalendarService::class)->monthView(2026, 8, $branchA->id);

        $this->assertArrayHasKey('2026-08-20', $view);
    }
}
