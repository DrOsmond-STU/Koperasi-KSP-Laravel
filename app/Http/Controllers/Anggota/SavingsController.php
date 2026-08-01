<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Models\SavingsAccount;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavingsController extends Controller
{
    public function index(Request $request): View
    {
        return view('portal.savings.index', [
            'accounts' => $request->user()->member->savingsAccounts()->with('savingsProduct')->get(),
        ]);
    }

    /**
     * Historis Simpanan per rekening — ownership check eksplisit di sini
     * (bukan cuma lewat query scoping) karena $account datang dari route
     * model binding, bisa saja ID rekening anggota lain diketik langsung di URL.
     */
    public function history(Request $request, SavingsAccount $account): View
    {
        abort_unless($account->member_id === $request->user()->member->id, 403);

        return view('portal.savings.history', [
            'account' => $account->load('savingsProduct'),
            'transactions' => $account->transactions()->limit(50)->get(),
        ]);
    }
}
