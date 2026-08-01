<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function index(Request $request): View
    {
        return view('portal.loans.index', [
            'loans' => $request->user()->member->loans()->with('loanProduct')->latest()->get(),
        ]);
    }

    /**
     * Jadwal & status angsuran per pinjaman — ownership check eksplisit
     * (route model binding tidak otomatis scope ke anggota yang login).
     */
    public function schedule(Request $request, Loan $loan): View
    {
        abort_unless($loan->member_id === $request->user()->member->id, 403);

        return view('portal.loans.schedule', [
            'loan' => $loan->load('loanProduct'),
            'schedules' => $loan->schedules,
        ]);
    }
}
