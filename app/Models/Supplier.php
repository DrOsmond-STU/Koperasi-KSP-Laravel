<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'contact_name',
        'phone',
        'address',
        'payment_term',
        'payment_term_days',
        'coa_payable_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_payable_account_id');
    }

    public function isKredit(): bool
    {
        return $this->payment_term === 'kredit';
    }
}
