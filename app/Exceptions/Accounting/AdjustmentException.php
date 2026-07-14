<?php

namespace App\Exceptions\Accounting;

use RuntimeException;

class AdjustmentException extends RuntimeException
{
    public static function reAuthFailed(): self
    {
        return new self('Konfirmasi ulang kata sandi gagal — koreksi jurnal tidak diproses.');
    }
}
