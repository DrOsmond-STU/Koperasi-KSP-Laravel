<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Menarik pengajuan pinjaman yang belum dicairkan.
 *
 * Sengaja TIDAK menuntut `pinjaman.delete` seperti pembatalan pencairan.
 * Keduanya menjaga hal yang berbeda: membatalkan pencairan membalik jurnal
 * yang uangnya sudah keluar, sedangkan pengajuan yang belum cair belum
 * menyentuh pembukuan sama sekali. Menuntut izin yang sama akan membuat
 * petugas kredit tidak bisa menarik kembali pengajuannya sendiri yang salah
 * input — padahal itu justru pekerjaan sehari-harinya.
 *
 * Penyempitan yang sebenarnya ada di controller lewat
 * Loan::canBeCancelledBy(): hanya pembuatnya sendiri, atau admin_sistem /
 * manajer.
 */
class CancelLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('pinjaman.create') || $user->can('pinjaman.approve');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'Alasan pembatalan'];
    }
}
