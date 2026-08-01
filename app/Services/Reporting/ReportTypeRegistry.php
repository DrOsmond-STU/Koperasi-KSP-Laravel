<?php

namespace App\Services\Reporting;

/**
 * Server-side whitelist of report types & their selectable columns (PRD
 * §17, Task 4.5). This IS the enforcement point for RPT-10 — a request
 * asking for a column not listed here is rejected by ReportBuilderService,
 * never trusted from client input. Adding a new report type is just a new
 * entry here plus a case in ReportBuilderService::fetchRawRows() — no
 * change to the whitelist-enforcement mechanism itself.
 *
 * Only the two report types named explicitly in 06_TESTING.md RPT-13
 * (Kartu Persediaan, Kartu Penyusutan) are wired up in this pass; the
 * mechanism generalizes to Simpanan/Pinjaman/UPF/Kas/etc. by adding more
 * entries, not by changing this class's shape.
 */
class ReportTypeRegistry
{
    /**
     * @return array<string, array{label: string, columns: array<string, string>}>
     */
    public static function definitions(): array
    {
        return [
            'persediaan_kartu' => [
                'label' => 'Kartu Persediaan (Rincian)',
                'columns' => [
                    'transaction_date' => 'Tanggal',
                    'transaction_type' => 'Jenis Transaksi',
                    'qty_in' => 'Qty Masuk',
                    'qty_out' => 'Qty Keluar',
                    'unit_cost' => 'Harga',
                    'running_qty' => 'Saldo Berjalan',
                ],
            ],
            'aktiva_tetap_kartu_penyusutan' => [
                'label' => 'Kartu Penyusutan Aktiva Tetap',
                'columns' => [
                    'period' => 'Periode',
                    'opening_book_value' => 'Nilai Buku Awal',
                    'depreciation_amount' => 'Penyusutan',
                    'closing_book_value' => 'Nilai Buku Akhir',
                ],
            ],
            // Kolom statis saja — kolom dinamis "retribusi_line_{id}" (satu per
            // jenis retribusi aktif saat request) dirakit oleh
            // RetributionController, bukan di sini (lihat class doc-comment:
            // registry ini tetap murni whitelist statis).
            'retribusi_upf' => [
                'label' => 'Retribusi UPF',
                'columns' => [
                    'transaction_date' => 'Tanggal',
                    'transaction_number' => 'No. Transaksi',
                    'payer_name' => 'Pembayar',
                    'member_number' => 'No. Anggota',
                    'total_amount' => 'Total Iuran',
                    'payment_method' => 'Metode Bayar',
                    'branch_name' => 'Cabang',
                    'created_by_name' => 'Kasir',
                    'description' => 'Keterangan',
                ],
            ],
        ];
    }

    public static function exists(string $reportType): bool
    {
        return isset(self::definitions()[$reportType]);
    }

    /**
     * @return array<string, string>
     */
    public static function columnsFor(string $reportType): array
    {
        return self::definitions()[$reportType]['columns'] ?? [];
    }

    public static function labelFor(string $reportType): ?string
    {
        return self::definitions()[$reportType]['label'] ?? null;
    }
}
