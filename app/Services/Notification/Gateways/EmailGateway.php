<?php

namespace App\Services\Notification\Gateways;

use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email fallback channel (PRD §16 — "Gagal kirim WhatsApp -> fallback
 * otomatis ke Email"). Sends via a Mailable (not Mail::raw()) so
 * Mail::fake()'s assertSent()/assertSentCount() work in tests — the
 * message body is already fully rendered by NotificationTemplateResolver.
 */
class EmailGateway
{
    public function send(string $toEmail, ?string $subject, string $message): NotificationSendResult
    {
        try {
            Mail::to($toEmail)->send(new NotificationMail($message, $subject ?? 'Pemberitahuan Koperasi'));
        } catch (Throwable $exception) {
            return new NotificationSendResult(success: false, cost: null, errorMessage: 'Email gagal terkirim.');
        }

        return new NotificationSendResult(success: true, cost: '0.00', errorMessage: null);
    }
}
