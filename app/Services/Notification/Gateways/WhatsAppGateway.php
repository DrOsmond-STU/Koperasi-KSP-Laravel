<?php

namespace App\Services\Notification\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Business API (official Meta/BSP integration, PRD §16) — never a
 * personal WhatsApp number. `retry()` + `throw()` together mean a non-2xx
 * response (API error/invalid number) is retried with backoff before the
 * caller (NotificationDispatchService) falls back to Email (NOTIF-03).
 */
class WhatsAppGateway
{
    public function send(string $toPhone, string $message): NotificationSendResult
    {
        try {
            $response = Http::withToken(config('services.whatsapp.token'))
                ->retry(3, 500)
                ->throw()
                ->post(config('services.whatsapp.base_url').'/'.config('services.whatsapp.phone_number_id').'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $toPhone,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);
        } catch (RequestException|ConnectionException $exception) {
            return new NotificationSendResult(success: false, cost: null, errorMessage: 'WhatsApp API error: HTTP '.($exception instanceof RequestException ? $exception->response->status() : 'connection failed'));
        }

        return new NotificationSendResult(success: true, cost: (string) ($response->json('cost') ?? '0.00'), errorMessage: null);
    }
}
