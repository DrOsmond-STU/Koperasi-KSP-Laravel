<?php

namespace App\Services\OpeningBalance;

/**
 * @property-read array<int, array{row: int, data: array<string, mixed>}> $validRows
 * @property-read array<int, array{row: int, errors: array<int, string>}> $errors
 */
class ImportResult
{
    public function __construct(
        public readonly array $validRows,
        public readonly array $errors,
    ) {}

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function totalRows(): int
    {
        return count($this->validRows) + count($this->errors);
    }
}
