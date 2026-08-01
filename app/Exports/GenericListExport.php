<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Reusable XLSX export for simple master-data/list screens — each caller
 * builds its own $rows (array of scalars per row, matching $headings
 * order), so this class carries no per-model knowledge and doesn't need
 * a new Export class for every list screen.
 */
class GenericListExport implements FromCollection, WithHeadings
{
    /**
     * @param  array<int, string>  $headings
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    public function __construct(
        private readonly array $headings,
        private readonly Collection $rows,
    ) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function collection(): Collection
    {
        return $this->rows;
    }
}
