<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu catatan penyelarasan jadwal angsuran dengan buku besar.
 *
 * Sengaja TIDAK memakai BelongsToBranch: ini jejak pemeliharaan sistem,
 * bukan transaksi milik satu cabang (sama seperti LoanBranchRepair).
 *
 * `payload` = { loan_id: { loan_number, status_lama, status_baru,
 *   baris: { schedule_id: { paid_principal_amount, paid_interest_amount,
 *   paid_amount, status } (nilai LAMA) } } }.
 */
class LoanScheduleAlignment extends Model
{
    protected $fillable = [
        'performed_by',
        'loans_aligned',
        'rows_changed',
        'payload',
        'reverted_at',
        'reverted_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reverted_at' => 'datetime',
        ];
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }

    /** @return array<int, string> */
    public function nomorPinjaman(): array
    {
        return array_values(array_map(fn (array $p) => (string) ($p['loan_number'] ?? '-'), $this->payload ?? []));
    }
}
