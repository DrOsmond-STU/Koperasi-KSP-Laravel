<?php

namespace App\Exceptions\OpeningBalance;

use RuntimeException;

class OpeningBalanceAdjustmentException extends RuntimeException
{
    public static function noChanges(): self
    {
        return new self('Tidak ada saldo yang berubah — tidak ada yang disimpan.');
    }

    public static function balancingAccountRequired(string $difference): self
    {
        return new self("Perubahan saldo tidak seimbang (selisih Rp {$difference}). Pilih akun penyeimbang untuk menampung selisih, atau sesuaikan saldo akun lain.");
    }

    public static function balancingAccountAlsoChanged(): self
    {
        return new self('Akun penyeimbang tidak boleh sekaligus diubah saldonya secara manual — pilih akun penyeimbang lain atau kembalikan saldonya.');
    }

    public static function reAuthFailed(): self
    {
        return new self('Konfirmasi ulang kata sandi gagal — koreksi saldo awal tidak diproses.');
    }

    public static function entryDateBeforeCutoff(string $cutoff): self
    {
        return new self("Tanggal jurnal koreksi tidak boleh sebelum tanggal cut-off ({$cutoff}).");
    }
}
