<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestSavingsDepositRequest;
use App\Models\SavingsAccount;
use App\Models\SavingsDepositRequest;
use App\Services\Savings\SavingsDepositGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Setor Simpanan Mandiri lewat Portal Anggota — berbeda dari Pengajuan
 * Penarikan, di sini tidak ada antrean persetujuan staf: begitu Xendit
 * konfirmasi lunas lewat webhook, saldo langsung terkredit otomatis.
 */
class SavingsDepositController extends Controller
{
    public function __construct(private readonly SavingsDepositGatewayService $deposits) {}

    public function create(Request $request, SavingsAccount $account): View
    {
        $member = $request->user()->member;

        abort_unless($account->member_id === $member->id, 403);

        return view('portal.savings.deposit', [
            'account' => $account,
            'gatewayConfigured' => filled(config('services.xendit.secret_key')),
            'history' => SavingsDepositRequest::query()
                ->where('savings_account_id', $account->id)
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(RequestSavingsDepositRequest $request, SavingsAccount $account): RedirectResponse
    {
        $member = $request->user()->member;

        abort_unless($account->member_id === $member->id, 403);
        abort_if(blank(config('services.xendit.secret_key')), 403, 'Fitur setoran mandiri belum aktif.');

        $depositRequest = $this->deposits->request($account, (float) $request->validated('amount'), $request->user()->id);

        return redirect($depositRequest->xendit_invoice_url);
    }
}
