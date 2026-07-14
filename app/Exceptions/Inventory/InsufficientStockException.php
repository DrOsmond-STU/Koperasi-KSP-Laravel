<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

/**
 * STK-07 (06_TESTING.md §3): penjualan/retur pembelian yang melebihi stok
 * berjalan ditolak; tidak ada mutasi stock_ledger negatif yang tercatat.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(string $productCode, string $available, string $requested)
    {
        parent::__construct("Stok barang \"{$productCode}\" ({$available}) tidak cukup untuk permintaan {$requested}.");
    }
}
