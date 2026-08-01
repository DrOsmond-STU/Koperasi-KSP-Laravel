<?php

namespace App\Exceptions\Savings;

use RuntimeException;

class WithdrawalRequestException extends RuntimeException
{
    public static function notPending(string $status): self
    {
        return new self("Pengajuan penarikan berstatus \"{$status}\" tidak dapat diproses lagi.");
    }

    public static function accountMismatch(): self
    {
        return new self('Rekening yang dipilih bukan milik anggota ini.');
    }
}
