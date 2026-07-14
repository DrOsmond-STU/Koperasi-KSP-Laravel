<?php

namespace App\Exceptions\FixedAsset;

use RuntimeException;

/**
 * Covers 06_TESTING.md AUTH-09/AUTH-12: pembelian aktiva tetap di atas
 * ambang nilai butuh approval dari orang lain, bukan pembuatnya sendiri.
 */
class FixedAssetApprovalException extends RuntimeException
{
    public static function selfApproval(): self
    {
        return new self('Pembuat aktiva tetap tidak boleh menyetujui pembeliannya sendiri (segregation of duties).');
    }

    public static function notPending(string $status): self
    {
        return new self("Aktiva tetap berstatus \"{$status}\" tidak dapat diproses lagi.");
    }
}
