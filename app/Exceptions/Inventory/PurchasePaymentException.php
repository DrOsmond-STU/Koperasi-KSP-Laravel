<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

class PurchasePaymentException extends RuntimeException
{
    public static function notYetPosted(): self
    {
        return new self('Pembayaran hutang hanya bisa dicatat untuk transaksi pembelian yang sudah diposting.');
    }

    public static function alreadyPaid(): self
    {
        return new self('Hutang untuk transaksi pembelian ini sudah lunas.');
    }

    public static function paymentExceedsRemaining(string $remaining, string $amount): self
    {
        return new self("Nominal pembayaran (Rp {$amount}) melebihi sisa hutang (Rp {$remaining}).");
    }
}
