<?php

namespace App\Exceptions\Savings;

use RuntimeException;

/**
 * LED-07 (06_TESTING.md §2): penarikan simpanan melebihi saldo ditolak
 * dengan pesan jelas; tidak ada jurnal yang terbentuk.
 */
class InsufficientBalanceException extends RuntimeException
{
    public function __construct(string $accountNumber, string $balance, string $requested)
    {
        parent::__construct("Saldo rekening {$accountNumber} (Rp {$balance}) tidak cukup untuk penarikan Rp {$requested}.");
    }
}
