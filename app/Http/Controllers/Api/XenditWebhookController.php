<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Loans\LoanRepaymentGatewayService;
use App\Services\Savings\SavingsDepositGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Xendit Invoice callback — the only source of truth that a payment
 * completed (the browser's success_redirect_url is UX only). Verified via
 * a shared X-Callback-Token, not HMAC — matches Xendit's classic Invoice
 * callback mechanism. Routes to the matching gateway service based on the
 * `external_id` prefix set at invoice-creation time (SETOR- / ANGSURAN-).
 */
class XenditWebhookController extends Controller
{
    public function __construct(
        private readonly SavingsDepositGatewayService $savingsDeposits,
        private readonly LoanRepaymentGatewayService $loanRepayments,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $token = (string) $request->header('x-callback-token', '');

        if (! hash_equals((string) config('services.xendit.webhook_token'), $token)) {
            return response()->json(['message' => 'Invalid webhook token.'], 403);
        }

        $status = (string) $request->input('status');
        $externalId = (string) $request->input('external_id');
        $amount = (float) $request->input('amount', 0);

        if ($status !== 'PAID' && $status !== 'SETTLED') {
            return response()->json(['message' => 'Ignored: status bukan PAID/SETTLED.']);
        }

        if (str_starts_with($externalId, 'SETOR-')) {
            $this->savingsDeposits->handleWebhookPaid($externalId, $amount);
        } elseif (str_starts_with($externalId, 'ANGSURAN-')) {
            $this->loanRepayments->handleWebhookPaid($externalId, $amount);
        } else {
            Log::warning('Xendit webhook: external_id tidak dikenali.', ['external_id' => $externalId]);
        }

        return response()->json(['message' => 'OK']);
    }
}
