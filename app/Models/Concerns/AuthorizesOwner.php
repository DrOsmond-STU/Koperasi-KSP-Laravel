<?php

namespace App\Models\Concerns;

use App\Models\User;

/**
 * "User tidak bisa mengubah/menghapus data user lain" (transaksi, bukan
 * akun) — hanya pembuat transaksi sendiri, atau role admin_sistem/manajer,
 * yang boleh membatalkan (void/reversal) transaksi yang sudah diposting.
 * Terapkan pada model dengan kolom `created_by`.
 */
trait AuthorizesOwner
{
    public function canBeCancelledBy(User $user): bool
    {
        return $user->id === $this->created_by || $user->hasAnyRole(['admin_sistem', 'manajer']);
    }
}
