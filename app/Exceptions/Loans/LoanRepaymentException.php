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

    public static function principalOverpayment(string $requested, string $outstanding): self
    {
        return new self("Angsuran Pokok Rp {$requested} melebihi sisa pokok di jadwal Rp {$outstanding}.");
    }

    public static function interestOverpayment(string $requested, string $outstanding): self
    {
        return new self("Jasa Rp {$requested} melebihi sisa jasa di jadwal Rp {$outstanding}. Kelebihannya bukan jasa — jangan dicatat sebagai jasa.");
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

    /**
     * Angsuran hasil impor riwayat (sebelum sistem dipakai) tidak punya
     * jurnal — nilainya sudah tercakup di Saldo Awal. Membatalkannya hanya
     * akan mengubah jadwal angsuran tanpa jurnal pembalik, sehingga jadwal
     * dan Buku Besar tidak cocok lagi.
     */
    public static function noJournal(): self
    {
        return new self('Angsuran ini hasil impor riwayat dan tidak punya jurnal (nilainya sudah termasuk di Saldo Awal), jadi tidak bisa dibatalkan dari sini. Koreksi lewat Saldo Awal atau Jurnal Umum.');
    }
}
