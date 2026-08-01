<?php

namespace App\Exceptions\Payment;

use RuntimeException;

/**
 * Xendit API call failed (non-2xx after retry, or connection failure).
 * Caught by the requesting service so it can mark the request row `gagal`
 * instead of leaving it dangling.
 */
class PaymentGatewayException extends RuntimeException
{
    public static function requestFailed(string $reason): self
    {
        return new self("Gagal menghubungi payment gateway: {$reason}");
    }
}
