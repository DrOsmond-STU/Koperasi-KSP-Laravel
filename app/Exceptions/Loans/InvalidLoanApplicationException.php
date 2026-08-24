<?php

namespace App\Exceptions\Loans;

use RuntimeException;

class InvalidLoanApplicationException extends RuntimeException
{
    public static function plafonOutOfRange(float $requested, float $min, float $max): self
    {
        return new self(sprintf(
            'Plafon Rp %s di luar rentang produk (Rp %s – Rp %s).',
            number_format($requested, 0, ',', '.'),
            number_format($min, 0, ',', '.'),
            number_format($max, 0, ',', '.'),
        ));
    }

    public static function tenorOutOfRange(int $requested, int $min, int $max, string $unit = 'bulan'): self
    {
        return new self("Tenor {$requested} {$unit} di luar rentang produk ({$min}–{$max} {$unit}).");
    }

    /** Produk lama (dibuat sebelum perbaikan tenor harian) belum punya rentang tenor harian — tidak bisa dipakai untuk pengajuan baru. */
    public static function productNotDailyTenor(string $productName): self
    {
        return new self("Produk \"{$productName}\" belum dikonfigurasi dengan tenor harian — hubungi admin untuk memperbarui produk ini.");
    }
}
