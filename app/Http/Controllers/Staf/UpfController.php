<?php

namespace App\Http\Controllers\Staf;

use App\Exceptions\Upf\FeeBillingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateFeeBillingRequest;
use App\Http\Requests\RecordFeePaymentRequest;
use App\Models\FeeBilling;
use App\Models\FeeType;
use App\Models\Tenant;
use App\Services\Upf\UpfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UpfController extends Controller
{
    public function __construct(private readonly UpfService $upfService) {}

    public function index(): View
    {
        $this->authorize('upf_tagihan.read');

        return view('staf.upf', [
            'tenants' => Tenant::query()->where('status', 'aktif')->get(),
            'feeTypes' => FeeType::query()->where('is_active', true)->get(),
            'outstandingBillings' => FeeBilling::query()
                ->whereIn('status', ['belum_bayar', 'sebagian'])
                ->with(['tenant', 'feeType'])
                ->latest()
                ->get(),
        ]);
    }

    public function generateBilling(GenerateFeeBillingRequest $request): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail($request->validated('tenant_id'));
        $feeType = FeeType::query()->findOrFail($request->validated('fee_type_id'));

        try {
            $billing = $this->upfService->generateBilling(
                $tenant,
                $feeType,
                $request->validated('period'),
                $request->user()->id,
                $request->validated('meter_start') !== null ? (float) $request->validated('meter_start') : null,
                $request->validated('meter_end') !== null ? (float) $request->validated('meter_end') : null,
            );
        } catch (FeeBillingException $exception) {
            return redirect()->route('staf.upf.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('staf.upf.index')
            ->with('status', "Tagihan {$feeType->name} kios {$tenant->code} sebesar Rp ".number_format($billing->amount, 0, ',', '.').' berhasil dibuat.');
    }

    public function recordPayment(RecordFeePaymentRequest $request): RedirectResponse
    {
        $billing = FeeBilling::query()->findOrFail($request->validated('fee_billing_id'));

        try {
            $this->upfService->recordPayment($billing, (float) $request->validated('amount'), $request->user()->id);
        } catch (FeeBillingException $exception) {
            return redirect()->route('staf.upf.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('staf.upf.index')->with('status', 'Pembayaran iuran berhasil dicatat.');
    }
}
