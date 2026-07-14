<?php

namespace App\Jobs;

use App\Models\LoanSchedule;
use App\Models\NotificationLog;
use App\Services\Notification\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * PRD §16, Task 4.3 — dispatched by CheckLoanDueDates for one
 * (loan_schedule, day_offset) combination. Re-validates the installment's
 * status right before sending (NOTIF-02): the schedule could have been
 * paid off in the time between being queued and this job actually running.
 */
class SendDueDateNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $loanScheduleId,
        public readonly string $dedupeKey,
    ) {}

    public function handle(NotificationDispatchService $dispatcher): void
    {
        $schedule = LoanSchedule::query()->with('loan.member', 'loan.branch')->find($this->loanScheduleId);

        if ($schedule === null) {
            return;
        }

        if ($schedule->status === 'lunas') {
            NotificationLog::query()->where('dedupe_key', $this->dedupeKey)->update([
                'status' => 'dibatalkan_lunas',
            ]);

            return;
        }

        $loan = $schedule->loan;
        $member = $loan->member;

        $dispatcher->send('jatuh_tempo_angsuran', $member, [
            'nama_anggota' => $member->name,
            'no_pinjaman' => $loan->loan_number,
            'nominal_angsuran' => 'Rp '.number_format((float) $schedule->total_amount, 0, ',', '.'),
            'tanggal_jatuh_tempo' => $schedule->due_date->translatedFormat('d M Y'),
            'nama_cabang' => $loan->branch->name,
        ], $this->dedupeKey);
    }
}
