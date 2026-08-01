<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Istilah "bunga" diganti "jasa" mengikuti terminologi koperasi. Seeder
 * chart_of_accounts.csv sudah memakai istilah baru, jadi migration ini hanya
 * merapikan database yang sudah terlanjur terisi istilah lama.
 *
 * Dikerjakan di PHP (bukan REGEXP_REPLACE) supaya jalan di MySQL 5.7 maupun
 * MariaDB di shared hosting, sekaligus supaya batas kata terjaga — REPLACE()
 * biasa akan merusak kata seperti "tabungan" dan "gabungan".
 */
return new class extends Migration
{
    /** Kolom teks yang boleh ikut diganti, per tabel. */
    private const TARGETS = [
        'chart_of_accounts' => ['name', 'notes'],
        'loan_products' => ['name'],
        'savings_products' => ['name'],
    ];

    /**
     * Akun bunga bank umum — pihak ketiga, bukan jasa koperasi ke anggota —
     * tetap memakai istilah "bunga" karena itu yang benar secara akuntansi.
     */
    private const EXCLUDED_ACCOUNT_CODES = ['4901', '5901'];

    public function up(): void
    {
        $this->rewrite(function (string $value): string {
            $value = str_replace(['Bunga/Jasa', 'Bunga/jasa'], 'Jasa', $value);
            $value = str_replace('Suku Bunga', 'Tarif Jasa', $value);
            $value = preg_replace('/\bBunga\b/u', 'Jasa', $value);

            return preg_replace('/\bbunga\b/u', 'jasa', $value);
        });
    }

    public function down(): void
    {
        // Tidak dibalik otomatis: "Jasa" juga dipakai istilah lain (mis.
        // "Pendapatan Sewa/Jasa Fasilitas"), jadi pembalikan buta justru
        // mengubah akun yang sejak awal memang bernama Jasa.
    }

    private function rewrite(callable $transform): void
    {
        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn($table, $column)
            ));

            if ($columns === []) {
                continue;
            }

            DB::table($table)
                ->when(
                    $table === 'chart_of_accounts' && Schema::hasColumn($table, 'code'),
                    fn ($query) => $query->whereNotIn('code', self::EXCLUDED_ACCOUNT_CODES)
                )
                ->select(array_merge(['id'], $columns))
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($table, $columns, $transform) {
                    foreach ($rows as $row) {
                        $changes = [];

                        foreach ($columns as $column) {
                            $current = $row->{$column};

                            if (! is_string($current) || $current === '') {
                                continue;
                            }

                            $updated = $transform($current);

                            if ($updated !== $current) {
                                $changes[$column] = $updated;
                            }
                        }

                        if ($changes !== []) {
                            DB::table($table)->where('id', $row->id)->update($changes);
                        }
                    }
                });
        }
    }
};
