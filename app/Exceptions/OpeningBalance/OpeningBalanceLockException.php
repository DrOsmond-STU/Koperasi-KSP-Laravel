<?php

namespace App\Exceptions\OpeningBalance;

use RuntimeException;

class OpeningBalanceLockException extends RuntimeException
{
    public static function notBalanced(): self
    {
        return new self('Saldo awal belum balance — periksa rekonsiliasi sebelum mengunci migrasi.');
    }

    public static function alreadyLocked(): self
    {
        return new self('Batch migrasi ini sudah dikunci sebelumnya.');
    }
}
