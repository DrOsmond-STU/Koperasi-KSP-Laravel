<?php

namespace App\Console\Commands;

use App\Jobs\SendDueDateNotification;
use App\Models\LoanSchedule;
use App\Models\NotificationLog;
use App\Models\NotificationSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * PRD §16, Task 4.3 — batch harian yang mengecek `loan_schedules` vs
 * `notification_schedules` (H-3/H-1/hari-H/terlambat, dikonfigurasi Admin,
 * bukan hardcode) dan mengantrikan SendDueDateNotification per kombinasi
 * yang cocok. `NotificationLog::createOrFirst()` mengklaim `dedupe_key`
 * secara atomik SEBELUM job diantrikan — inilah yang menjaga NOTIF-01
 * ("tidak dikirim dua kali") tetap benar walau command ini dijalankan
 * berulang kali untuk periode yang sama.
 */
#[Signature('notifikasi:cek-jatuh-tempo-angsuran')]
#[Description('Mengecek jadwal angsuran jatuh tempo vs notification_schedules dan mengantrikan reminder')]
class CheckLoanDueDates extends Command
{
    public function handle(): int
    {
        $today = now()->startOfDay();
        $dispatched = 0;

        NotificationSchedule::query()
            ->where('trigger_type', 'jatuh_tempo_angsuran')
            ->where('is_active', true)
            ->get()
            ->each(function (NotificationSchedule $schedule) use ($today, &$dispatched) {
                $targetDueDate = $today->copy()->subDays($schedule->day_offset)->toDateString();

                LoanSchedule::query()
                    ->where('due_date', $targetDueDate)
                    ->where('status', '!=', 'lunas')
                    ->whereHas('loan', fn ($query) => $query->where('status', 'dicairkan'))
                    ->get()
                    ->each(function (LoanSchedule $loanSchedule) use ($schedule, &$dispatched) {
                        $dedupeKey = "loan_schedule:{$loanSchedule->id}:offset:{$schedule->day_offset}";

                        $claim = NotificationLog::createOrFirst(
                            ['dedupe_key' => $dedupeKey],
                            [
                                'trigger_type' => 'jatuh_tempo_angsuran',
                                'channel' => 'whatsapp',
                                'recipient' => '-',
                                'message' => '',
                                'status' => 'menunggu',
                            ],
                        );

                        if ($claim->wasRecentlyCreated) {
                            SendDueDateNotification::dispatch($loanSchedule->id, $dedupeKey);
                            $dispatched++;
                        }
                    });
            });

        $this->info("Batch jatuh tempo angsuran: {$dispatched} notifikasi diantrikan.");

        return self::SUCCESS;
    }
}
