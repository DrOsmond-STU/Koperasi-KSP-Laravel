<?php

namespace App\Exceptions\Loans;

use RuntimeException;

class InvalidLoanApplicationException extends RuntimeException
{
    public static function plafonOutOfRange(float $requested, float $min, float $max): self
    {
        return new self(sprintf(
            'Plafon Rp %s di luar rentang produk (Rp %s – Rp %s).',
            number_format($requested, 0, ',', '.'),
            number_format($min, 0, ',', '.'),
            number_format($max, 0, ',', '.'),
        ));
    }

    public static function tenorOutOfRange(int $requested, int $min, int $max): self
    {
        return new self("Tenor {$requested} bulan di luar rentang produk ({$min}–{$max} bulan).");
    }
}
