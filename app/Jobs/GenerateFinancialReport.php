<?php

namespace App\Jobs;

use App\Exports\FinancialReportExport as FinancialReportXlsxExport;
use App\Models\FinancialReportExport;
use App\Services\Reporting\FinancialReportEngine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * PRD §17 — laporan keuangan diproses async lewat queue, sama seperti
 * Report Builder kustom, tapi tetap sebagai jalur/tabel terpisah karena
 * ini adalah format laporan wajib regulator, bukan laporan kustom.
 */
class GenerateFinancialReport implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $exportId) {}

    public function handle(FinancialReportEngine $engine): void
    {
        $export = FinancialReportExport::query()->find($this->exportId);

        if ($export === null) {
            return;
        }

        $export->update(['status' => 'memproses']);

        try {
            $data = match ($export->report_kind) {
                'neraca' => $engine->neraca($export->branch_id, $export->as_of_date->toDateString(), $export->basis),
                'laba_rugi' => $engine->labaRugi($export->branch_id, $export->period_start->toDateString(), $export->period_end->toDateString(), $export->basis),
                'arus_kas' => $engine->arusKas($export->branch_id, $export->period_start->toDateString(), $export->period_end->toDateString()),
                'calk' => $engine->calk($export->branch_id, $export->as_of_date->toDateString(), $export->basis),
            };

            $title = $this->titleFor($export->report_kind, $export->basis);
            $requestedBy = $export->requestedByUser;
            $filename = "laporan-keuangan-{$export->id}.{$export->format}";
            $path = "financial-report-exports/{$filename}";

            if ($export->format === 'pdf') {
                $html = view('admin.laporan-keuangan.pdf', [
                    'title' => $title,
                    'reportKind' => $export->report_kind,
                    'data' => $data,
                    'branchName' => $export->branch?->name,
                    'requestedByName' => $requestedBy->name,
                    'requestedAt' => $export->created_at->translatedFormat('d M Y H:i'),
                ])->render();

                Storage::disk('local')->put($path, Pdf::loadHTML($html)->output());
            } else {
                Excel::store(new FinancialReportXlsxExport($this->flattenRows($export->report_kind, $data)), $path, 'local');
            }

            $export->update([
                'status' => 'selesai',
                'file_path' => $path,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $export->update([
                'status' => 'gagal',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    private function titleFor(string $reportKind, string $basis): string
    {
        $standard = $basis === 'sak_emkm' ? 'SAK EMKM' : 'SAK EP';

        return match ($reportKind) {
            'neraca' => "Neraca ({$standard})",
            'laba_rugi' => "Laba Rugi / Perhitungan Hasil Usaha ({$standard})",
            'arus_kas' => "Laporan Arus Kas ({$standard})",
            'calk' => "Catatan atas Laporan Keuangan ({$standard})",
        };
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function flattenRows(string $reportKind, array $data): array
    {
        return match ($reportKind) {
            'neraca' => [
                ...collect($data['rows'])->map(fn (array $row) => [$row['account']->code.' - '.$row['account']->name, $row['balance']])->all(),
                ['Total Aset', $data['total_aset']],
                ['Total Liabilitas', $data['total_liabilitas']],
                ['Total Ekuitas', $data['total_ekuitas']],
            ],
            'laba_rugi' => [
                ...collect($data['rows'])->map(fn (array $row) => [$row['account']->code.' - '.$row['account']->name, $row['amount']])->all(),
                ['Total Pendapatan', $data['total_pendapatan']],
                ['Total Beban', $data['total_beban']],
                ['SHU', $data['shu']],
            ],
            'arus_kas' => [
                ['Saldo Awal', $data['opening_balance']],
                ['Total Masuk', $data['total_masuk']],
                ['Total Keluar', $data['total_keluar']],
                ['Saldo Akhir', $data['closing_balance']],
            ],
            'calk' => collect($data['groups'])
                ->flatMap(fn ($rows, $groupName) => collect($rows)->map(fn (array $row) => [$groupName.' - '.$row['account']->name, $row['balance']]))
                ->all(),
        };
    }
}
