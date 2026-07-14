<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Generic 2-column (Akun/Jumlah) XLSX export for Neraca/Laba Rugi/Arus
 * Kas/CALK — the row-building logic lives in GenerateFinancialReport so
 * the same flattened shape is used for both the on-screen preview and
 * this export (RPT-07: angka identik dengan layar).
 */
class FinancialReportExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, array{0: string, 1: string}>  $rows
     */
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return ['Akun', 'Jumlah'];
    }

    public function array(): array
    {
        return $this->rows;
    }
}
