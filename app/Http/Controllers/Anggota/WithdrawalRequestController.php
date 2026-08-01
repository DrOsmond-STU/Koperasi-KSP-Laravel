<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitWithdrawalRequestRequest;
use App\Models\SavingsAccount;
use App\Models\SavingsWithdrawalRequest;
use App\Services\Savings\SavingsWithdrawalRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengajuan Penarikan Simpanan lewat Portal Anggota — bukan eksekusi
 * langsung (butuh payment gateway pihak ketiga, di luar cakupan), tapi
 * antrean yang diproses staf lewat Staf\TellerController::decideWithdrawal().
 */
class WithdrawalRequestController extends Controller
{
    public function __construct(private readonly SavingsWithdrawalRequestService $withdrawalRequests) {}

    public function create(Request $request): View
    {
        $member = $request->user()->member;

        return view('portal.withdrawal-request', [
            'accounts' => $member->savingsAccounts()->where('status', 'aktif')->get(),
            'history' => SavingsWithdrawalRequest::query()
                ->where('member_id', $member->id)
                ->with('savingsAccount')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(SubmitWithdrawalRequestRequest $request): RedirectResponse
    {
        $member = $request->user()->member;
        $account = SavingsAccount::query()->findOrFail($request->validated('savings_account_id'));

        abort_unless($account->member_id === $member->id, 403, 'Rekening yang dipilih bukan milik Anda.');

        $withdrawalRequest = $this->withdrawalRequests->request($account, (float) $request->validated('amount'), $request->user()->id);

        return redirect()
            ->route('portal.withdrawal-request.create')
            ->with('status', 'Pengajuan penarikan Rp '.number_format($withdrawalRequest->amount, 0, ',', '.').' berhasil dikirim, menunggu persetujuan staf.');
    }
}
