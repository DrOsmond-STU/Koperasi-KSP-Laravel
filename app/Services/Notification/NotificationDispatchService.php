<?php

namespace App\Services\Notification;

use App\Models\Member;
use App\Models\NotificationLog;
use App\Services\Notification\Gateways\EmailGateway;
use App\Services\Notification\Gateways\WhatsAppGateway;
use Illuminate\Support\Facades\Log;

/**
 * Central notification orchestrator (PRD §16, Task 4.4). Every trigger
 * (jatuh tempo angsuran, kegiatan koperasi, ...) funnels through here so
 * consent, fallback, and logging are enforced exactly once. Never logs raw
 * message/phone content via Log:: (PII-02) — only NotificationLog (a
 * business record, not an application log) stores the actual text.
 */
class NotificationDispatchService
{
    /** NOTIF-08: crude anti-abuse heuristic — flag, don't block. */
    private const ABUSE_THRESHOLD_PER_HOUR = 100;

    public function __construct(
        private readonly NotificationTemplateResolver $templateResolver,
        private readonly WhatsAppGateway $whatsAppGateway,
        private readonly EmailGateway $emailGateway,
    ) {}

    /**
     * @param  array<string, string>  $variables
     */
    public function send(string $triggerType, Member $member, array $variables, string $dedupeKey): NotificationLog
    {
        $log = NotificationLog::query()->where('dedupe_key', $dedupeKey)->firstOrFail();

        $canWhatsApp = $member->hasConsent('whatsapp') && filled($member->phone);
        $canEmail = $member->hasConsent('email') && filled($member->email);

        if (! $canWhatsApp && ! $canEmail) {
            $log->update([
                'notifiable_type' => Member::class,
                'notifiable_id' => $member->id,
                'status' => 'tidak_dikirim_tanpa_consent',
            ]);

            return $log->fresh();
        }

        if ($canWhatsApp && $this->tryWhatsApp($log, $member, $triggerType, $variables)) {
            return $log->fresh();
        }

        if ($canEmail && $this->tryEmail($log, $member, $triggerType, $variables)) {
            return $log->fresh();
        }

        $log->update([
            'notifiable_type' => Member::class,
            'notifiable_id' => $member->id,
            'status' => 'gagal',
            'error_message' => $log->error_message ?? 'Tidak ada template aktif untuk trigger/channel ini.',
        ]);

        return $log->fresh();
    }

    private function tryWhatsApp(NotificationLog $log, Member $member, string $triggerType, array $variables): bool
    {
        $rendered = $this->templateResolver->resolve($triggerType, 'whatsapp', $variables);

        if ($rendered === null) {
            return false;
        }

        $this->flagIfVolumeSpike();

        $result = $this->whatsAppGateway->send($member->phone, $rendered['body']);

        if (! $result->success) {
            $log->update(['error_message' => $result->errorMessage]);

            return false;
        }

        $log->update([
            'notifiable_type' => Member::class,
            'notifiable_id' => $member->id,
            'channel' => 'whatsapp',
            'recipient' => $member->phone,
            'message' => $rendered['body'],
            'status' => 'terkirim',
            'cost' => $result->cost,
            'sent_at' => now(),
        ]);

        return true;
    }

    private function tryEmail(NotificationLog $log, Member $member, string $triggerType, array $variables): bool
    {
        $rendered = $this->templateResolver->resolve($triggerType, 'email', $variables);

        if ($rendered === null) {
            return false;
        }

        $result = $this->emailGateway->send($member->email, $rendered['subject'], $rendered['body']);

        $log->update([
            'notifiable_type' => Member::class,
            'notifiable_id' => $member->id,
            'channel' => 'email',
            'recipient' => $member->email,
            'subject' => $rendered['subject'],
            'message' => $rendered['body'],
            'status' => $result->success ? 'terkirim' : 'gagal',
            'cost' => $result->cost,
            'sent_at' => $result->success ? now() : null,
            'error_message' => $result->success ? null : $result->errorMessage,
        ]);

        return $result->success;
    }

    private function flagIfVolumeSpike(): void
    {
        $recentCount = NotificationLog::query()
            ->where('channel', 'whatsapp')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentCount > self::ABUSE_THRESHOLD_PER_HOUR) {
            Log::warning('notification.whatsapp.volume_spike', ['count_last_hour' => $recentCount + 1]);
        }
    }
}
