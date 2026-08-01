<?php

namespace App\Exceptions\Savings;

use RuntimeException;

class TransactionAlreadyCancelledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Transaksi ini sudah pernah dibatalkan sebelumnya.');
    }
}
