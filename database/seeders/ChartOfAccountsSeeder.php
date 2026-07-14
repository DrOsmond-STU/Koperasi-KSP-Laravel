<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds `chart_of_accounts` from database/seeders/data/chart_of_accounts.csv
 * (source of truth: SEED_CHART_OF_ACCOUNTS.md at the docs root).
 *
 * Validates before inserting (mirrors SEED_CHART_OF_ACCOUNTS.md "Catatan
 * untuk implementasi" #2): no duplicate `code`, every `parent_code` resolves
 * to a row in the file, `normal_balance` is DEBIT/KREDIT.
 *
 * Inserted in two passes so the self-referencing `parent_code` foreign key
 * is always satisfied regardless of row order in the CSV: pass 1 inserts
 * every row with parent_code temporarily null, pass 2 fills in the real
 * parent_code once every code exists.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/chart_of_accounts.csv');

        if (! file_exists($path)) {
            throw new RuntimeException("Chart of accounts CSV not found at {$path}");
        }

        $rows = $this->readCsv($path);
        $this->validate($rows);

        DB::transaction(function () use ($rows): void {
            ChartOfAccount::query()->delete();

            foreach ($rows as $row) {
                ChartOfAccount::query()->create([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'group' => $row['group'] !== '' ? $row['group'] : null,
                    'normal_balance' => $row['normal_balance'],
                    'is_postable' => $this->toBool($row['is_postable']),
                    'parent_code' => null,
                    'statement' => $row['statement'],
                    'notes' => $row['notes'] !== '' ? $row['notes'] : null,
                ]);
            }

            foreach ($rows as $row) {
                if ($row['parent_code'] === '') {
                    continue;
                }

                ChartOfAccount::query()
                    ->where('code', $row['code'])
                    ->update(['parent_code' => $row['parent_code']]);
            }
        });

        $this->command?->info(sprintf('Seeded %d chart of accounts rows.', count($rows)));
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $line);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function validate(array $rows): void
    {
        $codes = array_column($rows, 'code');

        if (count($codes) !== count(array_unique($codes))) {
            $duplicates = array_diff_assoc($codes, array_unique($codes));
            throw new RuntimeException('Duplicate chart of accounts code(s): '.implode(', ', $duplicates));
        }

        $codeSet = array_flip($codes);
        $validBalances = ['DEBIT', 'KREDIT'];

        foreach ($rows as $row) {
            if ($row['parent_code'] !== '' && ! isset($codeSet[$row['parent_code']])) {
                throw new RuntimeException("Account {$row['code']} references unknown parent_code {$row['parent_code']}");
            }

            if (! in_array($row['normal_balance'], $validBalances, true)) {
                throw new RuntimeException("Account {$row['code']} has invalid normal_balance: {$row['normal_balance']}");
            }
        }
    }

    private function toBool(string $value): bool
    {
        return strtoupper(trim($value)) === 'TRUE';
    }
}
