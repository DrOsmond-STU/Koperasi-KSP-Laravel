<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportMemberRequest;
use App\Models\Branch;
use App\Models\MemberType;
use App\Services\MemberImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import massal Master Anggota (PRD §9.2 Import Wizard) — dipisah dari
 * MemberController supaya CRUD anggota tetap tipis. Alur & pilihan mode
 * PERSIS mengikuti Saldo Awal: validate dulu, tampilkan baris gagal, commit
 * sesuai mode yang dipilih.
 */
class MemberImportController extends Controller
{
    public function __construct(private readonly MemberImportService $importService) {}

    public function form(): View
    {
        $this->authorize('master_data.create');

        return view('admin.anggota.import', [
            'branches' => Branch::query()->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
            'memberTypes' => MemberType::query()->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    public function import(ImportMemberRequest $request): RedirectResponse
    {
        $result = $this->importService->validate($request->file('file')->getRealPath());

        if ($result->totalRows() === 0) {
            return redirect()->route('admin.members.import.form')
                ->with('error', 'File tidak berisi baris data apa pun.');
        }

        if ($request->validated('mode') === 'all_or_nothing' && $result->hasErrors()) {
            return redirect()->route('admin.members.import.form')
                ->with('import_errors', $result->errors)
                ->with('error', 'Import dibatalkan — '.count($result->errors).' baris bermasalah, tidak ada yang disimpan (mode All-or-nothing).');
        }

        $committed = DB::transaction(fn () => $this->importService->commit($result->validRows));

        $message = "Import anggota: {$committed} baris berhasil disimpan";
        $message .= $result->hasErrors() ? ', '.count($result->errors).' baris gagal (lihat rincian).' : '.';

        return redirect()->route('admin.members.index')
            ->with('import_errors', $result->errors)
            ->with('status', $message);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->authorize('master_data.read');

        $headers = MemberImportService::HEADERS;
        $exampleRow = [
            '1170100001',
            'MUHATTAM',
            $this->firstCode(Branch::query()->orderBy('code')->value('code'), 'KSP'),
            $this->firstCode(MemberType::query()->orderBy('code')->value('code'), 'ANG-PENUH'),
            '',
            'KP. PULO RT.13/5 BOJONG GEDE, DEPOK',
            '',
            '',
            '',
            'Aktif',
            '2023-08-01',
        ];

        return response()->streamDownload(function () use ($headers, $exampleRow) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, $exampleRow);
            fclose($handle);
        }, 'template-import-anggota.csv', ['Content-Type' => 'text/csv']);
    }

    private function firstCode(?string $code, string $fallback): string
    {
        return $code !== null && $code !== '' ? $code : $fallback;
    }
}
