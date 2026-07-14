<?php

namespace App\Exceptions\Reporting;

use RuntimeException;

/**
 * Covers 06_TESTING.md RPT-10: kolom di luar whitelist ditolak SERVER,
 * bukan hanya disembunyikan di UI.
 */
class ReportBuilderException extends RuntimeException
{
    public static function unknownReportType(string $reportType): self
    {
        return new self("Jenis laporan \"{$reportType}\" tidak dikenal.");
    }

    /**
     * @param  array<int, string>  $invalidColumns
     */
    public static function columnsNotWhitelisted(array $invalidColumns): self
    {
        $list = implode(', ', $invalidColumns);

        return new self("Kolom berikut tidak diizinkan untuk jenis laporan ini: {$list}.");
    }
}
