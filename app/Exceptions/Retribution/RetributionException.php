<?php

namespace App\Exceptions\Retribution;

use RuntimeException;

class RetributionException extends RuntimeException
{
    public static function noActiveTypes(): self
    {
        return new self('Belum ada jenis retribusi yang aktif — aktifkan minimal satu jenis retribusi sebelum mencatat transaksi.');
    }

    public static function percentagesNotFullyAllocated(string $total): self
    {
        return new self("Total persentase jenis retribusi aktif belum 100% (saat ini {$total}%) — perbaiki dulu di Pengaturan Jenis Retribusi.");
    }

    public static function missingRevenueAccount(string $typeName): self
    {
        return new self("Jenis retribusi \"{$typeName}\" aktif tapi belum di-link ke akun COA — link-kan dulu di Pengaturan Jenis Retribusi.");
    }

    public static function alreadyCancelled(): self
    {
        return new self('Transaksi ini sudah pernah dibatalkan sebelumnya.');
    }
}
