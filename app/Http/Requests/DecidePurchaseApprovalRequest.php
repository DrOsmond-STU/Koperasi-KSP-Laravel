<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecidePurchaseApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * `pembelian.approve` is a distinct permission from `pembelian.create`
     * (segregation of duties — 02_SECURITY.md §Authorization).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('pembelian.approve') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['setuju', 'tolak'])],
        ];
    }
}
