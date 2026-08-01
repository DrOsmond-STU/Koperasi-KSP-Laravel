<?php

namespace App\Exceptions\FixedAsset;

use RuntimeException;

class FixedAssetDisposalException extends RuntimeException
{
    public static function notActive(string $status): self
    {
        return new self("Aktiva tetap berstatus \"{$status}\" tidak dapat dilepas — hanya aset Aktif yang bisa diproses.");
    }

    public static function alreadyCancelled(): self
    {
        return new self('Pelepasan aktiva tetap ini sudah pernah dibatalkan sebelumnya.');
    }
}
