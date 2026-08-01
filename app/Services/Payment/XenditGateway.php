<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\PaymentGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Xendit Invoice API — one call produces a hosted checkout page URL
 * supporting VA/e-wallet/QRIS/card at once, so the app never has to render
 * its own payment method UI. Structured after
 * App\Services\Notification\Gateways\WhatsAppGateway: Http:: facade with
 * retry()+throw(), no vendor SDK.
 */
class XenditGateway
{
    /**
     * @return array{id: string, invoice_url: string}
     */
    public function createInvoice(
        string $externalId,
        float $amount,
        string $description,
        string $payerEmail,
        string $successRedirectUrl,
    ): array {
        try {
            $response = Http::withBasicAuth((string) config('services.xendit.secret_key'), '')
                ->retry(3, 500)
                ->throw()
                ->post(config('services.xendit.base_url').'/v2/invoices', [
                    'external_id' => $externalId,
                    'amount' => $amount,
                    'description' => $description,
                    'payer_email' => $payerEmail,
                    'success_redirect_url' => $successRedirectUrl,
                    'currency' => 'IDR',
                ]);
        } catch (RequestException|ConnectionException $exception) {
            throw PaymentGatewayException::requestFailed(
                $exception instanceof RequestException ? 'HTTP '.$exception->response->status() : 'connection failed',
            );
        }

        return [
            'id' => (string) $response->json('id'),
            'invoice_url' => (string) $response->json('invoice_url'),
        ];
    }
}
