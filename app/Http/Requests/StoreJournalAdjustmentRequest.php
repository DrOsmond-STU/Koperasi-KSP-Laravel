<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreJournalAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('jurnal.adjust') ?? false;
    }

    /**
     * `password` is the re-auth confirmation (PRD §5.2) — checked in
     * AdjustmentService, not here, so the failure message stays consistent
     * with other re-auth failures rather than a generic validation error.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
