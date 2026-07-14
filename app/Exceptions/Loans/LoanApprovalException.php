<?php

namespace App\Exceptions\Loans;

use RuntimeException;

/**
 * Covers 06_TESTING.md AUTH-09 (pembuat != penyetuju) and AUTH-12
 * (approval berjenjang, tidak bisa satu langkah).
 */
class LoanApprovalException extends RuntimeException
{
    public static function selfApproval(): self
    {
        return new self('Pembuat pengajuan tidak boleh menyetujui pengajuannya sendiri (segregation of duties).');
    }

    public static function alreadyDecidedByThisUser(): self
    {
        return new self('Anda sudah memberikan keputusan untuk pengajuan pinjaman ini.');
    }

    public static function notPending(string $status): self
    {
        return new self("Pengajuan berstatus \"{$status}\" tidak dapat diproses lagi.");
    }
}
