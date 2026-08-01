<?php

namespace App\Exceptions\Payment;

use RuntimeException;

/**
 * `X-Callback-Token` header on an inbound Xendit webhook didn't match the
 * configured verification token — request is rejected before any row is
 * touched.
 */
class InvalidWebhookSignatureException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Token verifikasi webhook Xendit tidak valid.');
    }
}
