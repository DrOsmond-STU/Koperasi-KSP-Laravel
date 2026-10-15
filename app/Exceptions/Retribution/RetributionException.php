<?php

namespace App\Exceptions\Retribution;

use RuntimeException;

class RetributionException extends RuntimeException
{
    public static function noActiveSplitTypes(): self
    {
        return new self('Belum ada jenis retribusi "split" (persentase < 100%) yang aktif — transaksi Anggota membutuhkan minimal satu jenis split. Perbaiki dulu di Pengaturan Jenis Retribusi.');
    }

    public static function percentagesNotFullyAllocated(string $total): self
    {
        return new self("Total persentase jenis retribusi split (< 100%) yang aktif belum 100% (saat ini {$total}%) — perbaiki dulu di Pengaturan Jenis Retribusi sebelum mencatat transaksi Anggota.");
    }

    public static function missingRevenueAccount(string $typeName): self
    {
        return new self("Jenis retribusi \"{$typeName}\" aktif tapi belum di-link ke akun COA — link-kan dulu di Pengaturan Jenis Retribusi.");
    }

    public static function umumTypeMissing(): self
    {
        return new self('Transaksi Umum wajib memilih Jenis Retribusi (persentase 100%). Silakan pilih dari daftar Jenis Retribusi.');
    }

    public static function umumTypeInactive(string $typeName): self
    {
        return new self("Jenis retribusi \"{$typeName}\" tidak aktif — aktifkan dulu di Pengaturan Jenis Retribusi sebelum memakai untuk transaksi Umum.");
    }

    public static function umumTypeNotFull(string $typeName, string $percentage): self
    {
        return new self("Jenis retribusi \"{$typeName}\" tidak dapat dipakai untuk transaksi Umum karena persentasenya {$percentage}% (bukan 100%). Untuk transaksi Umum, pilih jenis retribusi dengan persentase 100%.");
    }

    public static function alreadyCancelled(): self
    {
        return new self('Transaksi ini sudah pernah dibatalkan sebelumnya.');
    }
}
