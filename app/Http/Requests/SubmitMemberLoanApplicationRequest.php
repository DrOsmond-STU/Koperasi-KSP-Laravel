<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Portal Anggota's own loan-application submission — deliberately has no
 * `member_id` field (unlike Staf\LoanApplicationController's
 * SubmitLoanApplicationRequest, which lets staff pick any member). The
 * member is always resolved server-side from the authenticated user's own
 * linked Member record, so a member can never apply on someone else's behalf.
 */
class SubmitMemberLoanApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->member !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'loan_product_id' => ['required', Rule::exists('loan_products', 'id')->where('is_active', true)],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'tenor_days' => ['required', 'integer', 'min:1'],
        ];
    }
}
