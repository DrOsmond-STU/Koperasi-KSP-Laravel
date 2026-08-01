<?php

namespace App\Exceptions\Loans;

use RuntimeException;

class LoanRepaymentException extends RuntimeException
{
    public static function notActive(string $status): self
    {
        return new self("Pinjaman berstatus \"{$status}\" tidak dapat menerima pembayaran angsuran.");
    }

    public static function overpayment(string $requested, string $outstanding): self
    {
        return new self("Nominal bayar Rp {$requested} melebihi total tunggakan saat ini Rp {$outstanding}.");
    }
}
