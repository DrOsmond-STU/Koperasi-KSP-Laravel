<?php

namespace App\Exceptions\Accounting;

use RuntimeException;

/**
 * Thrown by JournalEngine when a posting attempt violates ledger integrity
 * (06_TESTING.md §2: LED-02 unbalanced, LED-05 closed period, non-postable
 * account).
 */
class JournalPostingException extends RuntimeException
{
    public static function unbalanced(string $totalDebit, string $totalCredit): self
    {
        return new self("Jurnal tidak seimbang: total debit {$totalDebit} != total kredit {$totalCredit}.");
    }

    public static function emptyLines(): self
    {
        return new self('Jurnal harus memiliki minimal satu baris debit dan satu baris kredit.');
    }

    public static function nonPostableAccount(string $code): self
    {
        return new self("Akun {$code} adalah akun header (is_postable=false), tidak boleh dijadikan tujuan jurnal.");
    }

    public static function unknownAccount(string $code): self
    {
        return new self("Akun dengan kode {$code} tidak ditemukan di chart_of_accounts.");
    }

    public static function periodClosed(string $branchCode, string $entryDate): self
    {
        return new self("Periode akuntansi cabang {$branchCode} yang mencakup tanggal {$entryDate} sudah ditutup — posting mundur ditolak.");
    }
}
