<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\Loans\LoanPerMemberReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman & cetakan "Laporan Pinjaman per Anggota" — untuk setiap anggota,
 * menampilkan seluruh pinjaman (aktif/lunas/keduanya + filter periode)
 * lengkap dengan pokok, sisa outstanding, terbayar, dan rincian setiap
 * pembayaran (LoanRepayment) sebagai lampiran.
 */
class LoanPerMemberReportController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(private readonly LoanPerMemberReportService $reportService) {}

    public function index(Request $request): View
    {
        $this->authorize('laporan.read');

        $memberId = $request->integer('member_id') ?: null;
        $status = $request->input('status', 'all');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        $member = $memberId ? Member::query()->find($memberId) : null;

        $summary = $member ? $this->reportService->summarize($member, [
            'status' => $status,
            'period_start' => $periodStart ?: null,
            'period_end' => $periodEnd ?: null,
        ]) : null;

        return view('admin.laporan.pinjaman-per-anggota', [
            'members' => Member::query()->orderBy('name')->get(['id', 'name', 'member_number']),
            'selectedMember' => $member,
            'status' => $status,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'summary' => $summary,
        ]);
    }

    public function print(Request $request): Response
    {
        $this->authorize('laporan.read');

        $memberId = $request->integer('member_id') ?: null;
        abort_if($memberId === null, 400, 'Pilih anggota terlebih dahulu.');

        $member = Member::query()->findOrFail($memberId);
        $status = $request->input('status', 'all');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        $summary = $this->reportService->summarize($member, [
            'status' => $status,
            'period_start' => $periodStart ?: null,
            'period_end' => $periodEnd ?: null,
        ]);

        $pdf = $this->renderPrintPdf('prints.laporan.pinjaman-per-anggota', [
            'summary' => $summary,
            'status' => $status,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ], orientationOverride: 'landscape');

        $filename = 'pinjaman-anggota-'.($member->member_number ?: $member->id).'.pdf';

        return $pdf->stream($filename);
    }
}
