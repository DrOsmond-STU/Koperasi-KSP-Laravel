<?php

namespace App\Exceptions\Accounting;

use RuntimeException;

class AdjustmentException extends RuntimeException
{
    public static function reAuthFailed(): self
    {
        return new self('Konfirmasi ulang kata sandi gagal — koreksi jurnal tidak diproses.');
    }

    /**
     * Insiden nyata (entri #3780, Sep 2026): submit ganda pada tombol
     * "Posting Jurnal Balik" (double-click/resubmit) sempat membuat 2 jurnal
     * balik untuk entri yang sama karena tidak ada pengecekan ini sebelumnya.
     */
    public static function alreadyReversed(int $entryId): self
    {
        return new self("Jurnal #{$entryId} sudah pernah dibalik/dikoreksi sebelumnya — tidak bisa dibalik dua kali.");
    }
}
