<?php

namespace App\Exceptions\Loans;

use RuntimeException;

class LoanRepaymentException extends RuntimeException
{
    public static function notActive(string $status): self
    {
        return new self("Pinjaman berstatus \"{$status}\" tidak dapat menerima pembayaran angsuran.");
    }

    public static function overpayment(string $requested, string $outstanding): self
    {
        return new self("Nominal bayar Rp {$requested} melebihi total tunggakan saat ini Rp {$outstanding}.");
    }

    public static function zeroPayment(): self
    {
        return new self('Angsuran Pokok, Jasa, dan Denda tidak boleh ketiganya nol.');
    }

    public static function missingPenaltyAccount(): self
    {
        return new self('Produk pinjaman ini belum punya akun Piutang Denda — atur dulu di menu Master Produk Pinjaman sebelum mencatat Denda.');
    }

    public static function alreadyCancelled(): self
    {
        return new self('Angsuran ini sudah dibatalkan sebelumnya.');
    }
}
