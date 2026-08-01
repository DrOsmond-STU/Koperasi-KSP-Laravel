<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

/**
 * Covers 06_TESTING.md STK-05/STK-06: koreksi persediaan selisih minus
 * wajib approval dari orang lain (segregation of duties, lihat AUTH-09).
 */
class StockAdjustmentException extends RuntimeException
{
    public static function noVariance(): self
    {
        return new self('Stok fisik sama dengan stok sistem — tidak ada selisih untuk dikoreksi.');
    }

    public static function selfApproval(): self
    {
        return new self('Pembuat koreksi persediaan tidak boleh menyetujui koreksinya sendiri (segregation of duties).');
    }

    public static function notPending(string $status): self
    {
        return new self("Koreksi persediaan berstatus \"{$status}\" tidak dapat diproses lagi.");
    }

    public static function notPosted(string $status): self
    {
        return new self("Hanya koreksi persediaan yang sudah diposting yang bisa dibatalkan (status saat ini: \"{$status}\").");
    }

    public static function alreadyCancelled(): self
    {
        return new self('Koreksi persediaan ini sudah pernah dibatalkan sebelumnya.');
    }
}
