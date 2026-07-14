<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

class ReturnException extends RuntimeException
{
    public static function exceedsOriginalQty(string $available, string $requested): self
    {
        return new self("Qty retur ({$requested}) melebihi sisa qty yang bisa diretur ({$available}) dari transaksi asal.");
    }

    public static function purchaseNotPosted(): self
    {
        return new self('Hanya transaksi pembelian yang sudah diposting yang bisa diretur.');
    }
}
