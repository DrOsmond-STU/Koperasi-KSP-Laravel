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

    public static function notDisbursed(string $status): self
    {
        return new self("Pinjaman berstatus \"{$status}\" tidak dapat dibatalkan pencairannya.");
    }

    public static function alreadyCancelled(): self
    {
        return new self('Pencairan pinjaman ini sudah dibatalkan sebelumnya.');
    }

    public static function hasPayments(): self
    {
        return new self('Pinjaman sudah memiliki angsuran terbayar — pembatalan pencairan tidak dapat dilakukan (gunakan restrukturisasi).');
    }

    public static function noDisbursementJournal(): self
    {
        return new self('Pinjaman ini tidak memiliki jurnal pencairan tersendiri (berasal dari transaksi lain) dan tidak dapat dibatalkan lewat menu ini.');
    }
}
