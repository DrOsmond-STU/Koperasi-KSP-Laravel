<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Member;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetakan Simpanan Anggota — semua anggota atau satu anggota (?member_id=),
 * gate simpanan.print. Pola sama dengan MemberController::printList().
 */
class PrintSavingsController extends Controller
{
    use GeneratesPrintPdf;

    public function index(Request $request): Response
    {
        $this->authorize('simpanan.print');

        $memberId = $request->integer('member_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;

        $allowedBranchIds = $request->user()->allowedBranchIds();
        if ($branchId !== null && $allowedBranchIds !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $members = Member::query()
            ->with(['savingsAccounts.savingsProduct'])
            ->when($memberId, fn ($query) => $query->where('id', $memberId))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        $filterDescription = collect([
            $memberId ? 'Anggota: '.Member::query()->find($memberId)?->name : null,
            $branchId ? 'Cabang: '.Branch::query()->find($branchId)?->name : null,
        ])->filter()->implode(' — ') ?: 'Semua Anggota';

        $pdf = $this->renderPrintPdf('prints.savings.list', [
            'members' => $members,
            'filterDescription' => $filterDescription,
            'generatedAt' => now(),
        ]);

        return $pdf->download('cetakan-simpanan-'.now()->format('Ymd-His').'.pdf');
    }
}
