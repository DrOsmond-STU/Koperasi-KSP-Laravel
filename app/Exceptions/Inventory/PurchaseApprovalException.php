<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

/**
 * Covers 06_TESTING.md AUTH-09/AUTH-12: pembelian di atas ambang nilai
 * butuh approval dari orang lain, bukan pembuatnya sendiri.
 */
class PurchaseApprovalException extends RuntimeException
{
    public static function selfApproval(): self
    {
        return new self('Pembuat transaksi pembelian tidak boleh menyetujui pembeliannya sendiri (segregation of duties).');
    }

    public static function notPending(string $status): self
    {
        return new self("Transaksi pembelian berstatus \"{$status}\" tidak dapat diproses lagi.");
    }
}
