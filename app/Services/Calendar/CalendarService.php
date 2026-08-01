<?php

namespace App\Services\Calendar;

use App\Models\CooperativeEvent;
use App\Models\LoanSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dashboard Kalender (PRD §15/§15.1, Task 4.1) — merges automatic system
 * events (jatuh tempo angsuran) with manual Kegiatan Koperasi into one
 * day-grouped list, filtered by Cabang Scope.
 *
 * "Jatuh tempo simpanan berjangka" from PRD §15 is NOT included: Phase 1's
 * `savings_accounts`/`savings_products` schema has no maturity/tenor field
 * for time-deposit products yet (out of scope for this task — would need a
 * savings-product schema change, not just a calendar read-model).
 */
class CalendarService
{
    /**
     * @return array<string, Collection<int, array<string, mixed>>> keyed by 'Y-m-d'
     */
    public function monthView(int $year, int $month, ?int $branchId): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $items = collect();

        $this->loanDueDates($start, $end, $branchId)->each(fn (array $item) => $items->push($item));
        $this->cooperativeEvents($start, $end, $branchId)->each(fn (array $item) => $items->push($item));

        return $items->groupBy('date')->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loanDueDates(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        return LoanSchedule::query()
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'lunas')
            ->whereHas('loan', function ($query) use ($branchId) {
                $query->where('status', 'dicairkan');

                if ($branchId !== null) {
                    $query->where('branch_id', $branchId);
                }
            })
            ->with('loan.member')
            ->get()
            ->map(fn (LoanSchedule $schedule) => [
                'type' => 'jatuh_tempo_angsuran',
                'date' => $schedule->due_date->toDateString(),
                'title' => $schedule->loan->member->name.' — Rp '.number_format((float) $schedule->total_amount, 0, ',', '.'),
                'detail' => "Angsuran ke-{$schedule->installment_number} — pinjaman {$schedule->loan->loan_number}",
                'member_name' => $schedule->loan->member->name,
                'loan_number' => $schedule->loan->loan_number,
                'installment_number' => $schedule->installment_number,
                'amount' => (float) $schedule->total_amount,
                'color' => 'merah',
                'ref_id' => $schedule->id,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function cooperativeEvents(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        return CooperativeEvent::query()
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_at', [$start, $end])
                    ->orWhereBetween('end_at', [$start, $end]);
            })
            ->get()
            ->filter(function (CooperativeEvent $event) use ($branchId) {
                if ($branchId === null) {
                    return true;
                }

                $ids = $event->branchIds();

                return $ids === null || in_array($branchId, $ids, true);
            })
            ->map(fn (CooperativeEvent $event) => [
                'type' => 'kegiatan',
                'date' => $event->start_at->toDateString(),
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'start_at' => $event->start_at->toDateTimeString(),
                'end_at' => $event->end_at->toDateTimeString(),
                'color' => $event->isCancelled() ? 'abu' : 'biru',
                'status' => $event->status,
                'ref_id' => $event->id,
            ]);
    }
}
