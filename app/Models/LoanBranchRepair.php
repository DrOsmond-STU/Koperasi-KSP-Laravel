<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu catatan pemindahan kegiatan pinjaman ke sebuah cabang.
 *
 * Sengaja TIDAK memakai BelongsToBranch: catatan ini adalah jejak
 * pemeliharaan sistem, bukan transaksi milik satu cabang. Kalau ia ikut
 * disaring BranchScope, pengurus yang cakupannya satu cabang tidak akan
 * melihat riwayat perbaikannya sendiri.
 */
class LoanBranchRepair extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_branch_id',
        'performed_by',
        'loans_moved',
        'repayments_moved',
        'entries_moved',
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

    public function targetBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'target_branch_id');
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

    public function totalMoved(): int
    {
        return $this->loans_moved + $this->repayments_moved + $this->entries_moved;
    }
}
