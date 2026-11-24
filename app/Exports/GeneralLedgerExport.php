<?php

namespace App\Exports;

use App\Http\Controllers\Admin\GeneralLedgerController;
use App\Models\Branch;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Kartu Buku Besar satu akun ke XLSX — isi persis sama dengan layar
 * (GeneralLedgerController::dataKartu()): saldo awal periode, mutasi, saldo
 * berjalan, lalu baris total. Tanggal sebagai tanggal Excel dan nominal
 * sebagai angka (bukan teks) supaya bisa langsung dijumlah/difilter.
 */
class GeneralLedgerExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStrictNullComparison, WithStyles, WithTitle
{
    private const BARIS_JUDUL_TABEL = 7;

    public function __construct(private readonly array $data) {}

    public function title(): string
    {
        return mb_substr('Buku Besar '.$this->data['selectedAccount']->code, 0, 31);
    }

    public function array(): array
    {
        $akun = $this->data['selectedAccount'];
        $tgl = fn (string $d) => Carbon::parse($d)->translatedFormat('d M Y');
        $cabang = $this->data['selectedBranchId'] === null
            ? 'Konsolidasi Seluruh Cabang'
            : (string) Branch::query()->whereKey($this->data['selectedBranchId'])->value('name');

        $rows = [
            ['BUKU BESAR'],
            ['Akun', $akun->code.' — '.$akun->name],
            ['Cabang', $cabang],
            ['Periode', $tgl($this->data['periodStart']).' s/d '.$tgl($this->data['periodEnd'])],
            ['Jurnal dibatalkan', $this->data['showCancelled'] ? 'ditampilkan' : 'disembunyikan (saldo tidak terpengaruh)'],
            [''], // baris kosong — array kosong dilewati maatwebsite, judul tabel jadi bergeser
            ['Tanggal', 'No. Jurnal', 'Keterangan', 'Debit', 'Kredit', 'Saldo Berjalan'],
        ];

        foreach ($this->data['lines'] as $line) {
            $awal = $line['is_opening'] ?? false;
            $rows[] = [
                ExcelDate::PHPToExcel(Carbon::parse($line['date'])->startOfDay()),
                $awal ? '' : '#'.($line['entry_id'] ?? ''),
                $line['description'],
                // 0 dikosongkan (seperti di layar); baris total tetap menulis 0.
                $awal || (float) $line['debit'] == 0 ? null : (float) $line['debit'],
                $awal || (float) $line['credit'] == 0 ? null : (float) $line['credit'],
                (float) $line['running_balance'],
            ];
        }

        $r = GeneralLedgerController::ringkasan($this->data['lines']);
        $rows[] = ['', '', 'TOTAL MUTASI', $r['total_debit'], $r['total_kredit'], $r['saldo_akhir']];

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'A' => 'dd/mm/yyyy',
            'D' => '#,##0.00',
            'E' => '#,##0.00',
            'F' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $akhir = $sheet->getHighestRow();
        // Baris judul info (A2:A5) jangan ikut format tanggal.
        $sheet->getStyle('A1:A'.(self::BARIS_JUDUL_TABEL - 1))->getNumberFormat()->setFormatCode('General');

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            'A2:A5' => ['font' => ['bold' => true]],
            self::BARIS_JUDUL_TABEL => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E9F1EC']]],
            self::BARIS_JUDUL_TABEL + 1 => ['font' => ['italic' => true, 'bold' => true]],
            $akhir => ['font' => ['bold' => true], 'borders' => ['top' => ['borderStyle' => 'thin']]],
        ];
    }
}
