<?php

namespace Tests\Feature\Notification;

use App\Jobs\SendDueDateNotification;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\NotificationLog;
use App\Models\NotificationSchedule;
use App\Services\Notification\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md NOTIF-01 (batch mendeteksi H-3/H-1/hari-H sesuai
 * notification_schedules, tidak dikirim dua kali untuk kombinasi yang
 * sama) and NOTIF-02 (angsuran lunas sebelum job jalan -> tidak terkirim).
 */
class CheckLoanDueDatesTest extends TestCase
{
    use RefreshDatabase;

    private function unpaidScheduleDueIn(int $days): LoanSchedule
    {
        $loan = Loan::factory()->create(['status' => 'dicairkan']);

        return LoanSchedule::query()->create([
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'due_date' => now()->addDays($days)->toDateString(),
            'principal_amount' => 400000,
            'interest_amount' => 50000,
            'total_amount' => 450000,
            'status' => 'belum_bayar',
        ]);
    }

    public function test_command_dispatches_reminder_for_configured_h3_offset(): void
    {
        Queue::fake();
        NotificationSchedule::query()->create(['trigger_type' => 'jatuh_tempo_angsuran', 'day_offset' => -3, 'send_time' => '08:00:00']);

        $schedule = $this->unpaidScheduleDueIn(3);

        $this->artisan('notifikasi:cek-jatuh-tempo-angsuran')->assertExitCode(0);

        Queue::assertPushed(SendDueDateNotification::class, fn ($job) => $job->loanScheduleId === $schedule->id);
        $this->assertDatabaseHas('notification_logs', [
            'dedupe_key' => "loan_schedule:{$schedule->id}:offset:-3",
            'status' => 'menunggu',
        ]);
    }

    public function test_command_does_not_dispatch_twice_for_the_same_combination(): void
    {
        Queue::fake();
        NotificationSchedule::query()->create(['trigger_type' => 'jatuh_tempo_angsuran', 'day_offset' => -1, 'send_time' => '08:00:00']);
        $this->unpaidScheduleDueIn(1);

        $this->artisan('notifikasi:cek-jatuh-tempo-angsuran');
        $this->artisan('notifikasi:cek-jatuh-tempo-angsuran');

        Queue::assertPushed(SendDueDateNotification::class, 1);
        $this->assertDatabaseCount('notification_logs', 1);
    }

    public function test_job_skips_sending_when_installment_already_paid(): void
    {
        Http::fake();
        $schedule = $this->unpaidScheduleDueIn(0);
        $schedule->update(['status' => 'lunas']);

        $dedupeKey = "loan_schedule:{$schedule->id}:offset:0";
        NotificationLog::query()->create([
            'dedupe_key' => $dedupeKey,
            'trigger_type' => 'jatuh_tempo_angsuran',
            'channel' => 'whatsapp',
            'recipient' => '-',
            'message' => '',
            'status' => 'menunggu',
        ]);

        (new SendDueDateNotification($schedule->id, $dedupeKey))->handle(app(NotificationDispatchService::class));

        Http::assertNothingSent();
        $this->assertDatabaseHas('notification_logs', ['dedupe_key' => $dedupeKey, 'status' => 'dibatalkan_lunas']);
    }
}
