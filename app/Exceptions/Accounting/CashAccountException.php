<?php

namespace App\Exceptions\Accounting;

use RuntimeException;

/**
 * Dilempar CashAccountResolver ketika akun kas lawan sebuah transaksi tidak
 * bisa ditentukan, atau ketentuannya tidak layak posting.
 *
 * Dipisah dari JournalPostingException supaya pesannya bisa menyebut apa
 * yang harus diperbaiki (cabang mana, di layar mana) — bukan sekadar
 * "akun sekian adalah akun header", yang benar tapi tidak memberi tahu
 * siapa pun harus berbuat apa.
 */
class CashAccountException extends RuntimeException
{
    public static function branchHasNoCashAccount(string $branchName): self
    {
        return new self("Cabang \"{$branchName}\" belum punya akun kas, dan tidak ada akun kas bawaan yang bisa dipakai. Atur di Pengaturan → Kas Cabang.");
    }

    public static function noBranchAndNoDefault(): self
    {
        return new self('Transaksi ini tidak terikat cabang mana pun dan tidak ada akun kas bawaan yang bisa dipakai. Atur akun kas cabang di Pengaturan → Kas Cabang, atau isi KOPERASI_AKUN_KAS_BAWAAN.');
    }

    public static function defaultAccountMissing(string $code): self
    {
        return new self("Akun kas bawaan berkode \"{$code}\" tidak ada di Bagan Akun. Perbaiki akun kas cabangnya di Pengaturan → Kas Cabang, atau arahkan KOPERASI_AKUN_KAS_BAWAAN ke akun yang benar.");
    }

    public static function branchNotFound(string $branchCode): self
    {
        return new self("Cabang berkode \"{$branchCode}\" tidak ditemukan, sehingga akun kasnya tidak bisa ditentukan.");
    }

    public static function notPostable(string $code, string $name, string $source): self
    {
        return new self("Akun kas \"{$code} — {$name}\" ({$source}) adalah akun header sehingga tidak bisa dijadikan tujuan jurnal. Pilih akun kas yang bisa diposting di Pengaturan → Kas Cabang.");
    }
}
