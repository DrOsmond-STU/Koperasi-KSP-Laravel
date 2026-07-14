<?php

namespace App\Exceptions\Upf;

use RuntimeException;

class FeeBillingException extends RuntimeException
{
    public static function alreadyBilled(string $period): self
    {
        return new self("Tagihan untuk periode {$period} sudah pernah dibuat untuk kios & jenis iuran ini.");
    }

    public static function meterReadingRequired(): self
    {
        return new self('Jenis iuran ini berbasis meteran — meter_start dan meter_end wajib diisi.');
    }

    public static function meterEndBeforeStart(): self
    {
        return new self('meter_end tidak boleh lebih kecil dari meter_start.');
    }

    public static function paymentExceedsRemaining(string $remaining, string $amount): self
    {
        return new self("Nominal pembayaran (Rp {$amount}) melebihi sisa tagihan (Rp {$remaining}).");
    }

    public static function billingAlreadyPaid(): self
    {
        return new self('Tagihan ini sudah lunas.');
    }
}
