<?php

namespace Tests\Feature\Notification;

use App\Models\Member;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\Notification\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md NOTIF-03 (WA gagal -> retry lalu fallback Email),
 * NOTIF-04 (consent WA ditarik -> otomatis Email), NOTIF-05 (kedua consent
 * ditarik -> tidak dikirim, bukan gagal), and NOTIF-08 (lonjakan volume ->
 * flag anti-abuse).
 */
class NotificationDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private function memberWithConsent(bool $whatsapp, bool $email): Member
    {
        $member = Member::factory()->create();

        $member->consents()->create(['channel' => 'whatsapp', 'consented' => $whatsapp, 'consented_at' => $whatsapp ? now() : null, 'withdrawn_at' => $whatsapp ? null : now()]);
        $member->consents()->create(['channel' => 'email', 'consented' => $email, 'consented_at' => $email ? now() : null, 'withdrawn_at' => $email ? null : now()]);

        return $member;
    }

    private function claim(string $dedupeKey): NotificationLog
    {
        return NotificationLog::query()->create([
            'dedupe_key' => $dedupeKey,
            'trigger_type' => 'jatuh_tempo_angsuran',
            'channel' => 'whatsapp',
            'recipient' => '-',
            'message' => '',
            'status' => 'menunggu',
        ]);
    }

    public function test_sends_via_whatsapp_when_consented_and_api_succeeds(): void
    {
        NotificationTemplate::factory()->create(['trigger_type' => 'jatuh_tempo_angsuran', 'channel' => 'whatsapp', 'body' => 'Halo {nama_anggota}.']);
        Http::fake(['*' => Http::response(['cost' => '150.00'], 200)]);

        $member = $this->memberWithConsent(whatsapp: true, email: true);
        $this->claim('dedupe-1');

        $log = app(NotificationDispatchService::class)->send('jatuh_tempo_angsuran', $member, ['nama_anggota' => $member->name], 'dedupe-1');

        $this->assertEquals('terkirim', $log->status);
        $this->assertEquals('whatsapp', $log->channel);
    }

    public function test_falls_back_to_email_when_whatsapp_api_fails(): void
    {
        NotificationTemplate::factory()->create(['trigger_type' => 'jatuh_tempo_angsuran', 'channel' => 'whatsapp', 'body' => 'Halo {nama_anggota}.']);
        NotificationTemplate::factory()->create(['trigger_type' => 'jatuh_tempo_angsuran', 'channel' => 'email', 'subject' => 'Jatuh Tempo', 'body' => 'Halo {nama_anggota}.']);
        Http::fake(['*' => Http::response(['error' => 'invalid number'], 400)]);
        Mail::fake();

        $member = $this->memberWithConsent(whatsapp: true, email: true);
        $this->claim('dedupe-2');

        $log = app(NotificationDispatchService::class)->send('jatuh_tempo_angsuran', $member, ['nama_anggota' => $member->name], 'dedupe-2');

        $this->assertEquals('terkirim', $log->status);
        $this->assertEquals('email', $log->channel);
        Mail::assertSentCount(1);
    }

    public function test_withdrawn_whatsapp_consent_routes_directly_to_email(): void
    {
        NotificationTemplate::factory()->create(['trigger_type' => 'jatuh_tempo_angsuran', 'channel' => 'email', 'subject' => 'Jatuh Tempo', 'body' => 'Halo {nama_anggota}.']);
        Mail::fake();
        Http::fake();

        $member = $this->memberWithConsent(whatsapp: false, email: true);
        $this->claim('dedupe-3');

        $log = app(NotificationDispatchService::class)->send('jatuh_tempo_angsuran', $member, ['nama_anggota' => $member->name], 'dedupe-3');

        $this->assertEquals('terkirim', $log->status);
        $this->assertEquals('email', $log->channel);
        Http::assertNothingSent();
    }

    public function test_withdrawing_both_consents_marks_not_sent_without_consent_not_failed(): void
    {
        Http::fake();
        Mail::fake();

        $member = $this->memberWithConsent(whatsapp: false, email: false);
        $this->claim('dedupe-4');

        $log = app(NotificationDispatchService::class)->send('jatuh_tempo_angsuran', $member, ['nama_anggota' => $member->name], 'dedupe-4');

        $this->assertEquals('tidak_dikirim_tanpa_consent', $log->status);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_volume_spike_flags_via_log_warning_without_leaking_message_content(): void
    {
        NotificationTemplate::factory()->create(['trigger_type' => 'jatuh_tempo_angsuran', 'channel' => 'whatsapp', 'body' => 'Rahasia {nama_anggota}.']);
        Http::fake(['*' => Http::response(['cost' => '0'], 200)]);

        for ($i = 0; $i < 101; $i++) {
            NotificationLog::query()->create([
                'dedupe_key' => "bulk-{$i}",
                'trigger_type' => 'jatuh_tempo_angsuran',
                'channel' => 'whatsapp',
                'recipient' => '-',
                'message' => '',
                'status' => 'terkirim',
                'sent_at' => now(),
            ]);
        }

        Log::spy();

        $member = $this->memberWithConsent(whatsapp: true, email: true);
        $this->claim('dedupe-5');
        app(NotificationDispatchService::class)->send('jatuh_tempo_angsuran', $member, ['nama_anggota' => $member->name], 'dedupe-5');

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) {
                return $message === 'notification.whatsapp.volume_spike'
                    && ! str_contains(json_encode($context), 'Rahasia');
            })
            ->once();
    }
}
