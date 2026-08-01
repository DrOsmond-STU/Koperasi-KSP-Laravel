<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Models\LoanSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalController extends Controller
{
    /**
     * Ringkasan Portal Anggota — semua data diambil lewat $request->user()->member,
     * tidak pernah dari input, sehingga tidak mungkin melihat data anggota lain.
     */
    public function dashboard(Request $request): View
    {
        $member = $request->user()->member;

        $savingsAccounts = $member->savingsAccounts()->where('status', 'aktif')->with('savingsProduct')->get();
        $activeLoans = $member->loans()->where('status', 'dicairkan')->get();

        $dueInstallments = LoanSchedule::query()
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
            ->with('loan')
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        return view('portal.dashboard', [
            'member' => $member,
            'totalSavings' => (float) $savingsAccounts->sum('balance'),
            'totalLoanOutstanding' => (float) $activeLoans->sum('principal_amount'),
            'savingsAccounts' => $savingsAccounts,
            'activeLoans' => $activeLoans,
            'dueInstallments' => $dueInstallments,
        ]);
    }
}
