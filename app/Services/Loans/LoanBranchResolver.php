<?php

namespace App\Services\Loans;

use App\Services\Settings\CashSettingsService;

/**
 * Cabang yang dibukukan pada transaksi pinjaman — pengajuan, pencairan,
 * dan angsuran.
 *
 * Sebelumnya cabangnya diturunkan dari cabang si ANGGOTA
 * (`members.branch_id`). Itu menjawab pertanyaan yang salah: `branch_id`
 * pada transaksi dipakai untuk laba rugi per unit usaha, jadi yang harus
 * tercatat adalah unit yang MENJALANKAN pinjamannya, bukan unit tempat
 * anggotanya terdaftar. Anggota UPF yang meminjam tetap meminjam dari unit
 * simpan pinjam, dan pendapatan jasanya milik unit itu.
 *
 * Hasilnya di produksi 16 Sep 2026: tidak ada satu pun jurnal pinjaman di
 * cabang USP — 1 pencairan tercatat di KSP dan seluruh 1.090 jurnal
 * angsuran di cabang root KPPD Pusat, sehingga laba rugi USP kosong
 * padahal seluruh kegiatan pinjaman memang milik USP.
 *
 * Dipakai di DUA titik — pengajuan dan pencairan — dan pencairan sekalian
 * membetulkan `loans.branch_id` barisnya. Angsuran sengaja TIDAK memakai
 * resolver ini dan tetap ikut `loans.branch_id`, karena satu-satunya
 * sumber kebenaran cabang sebuah pinjaman adalah baris pinjaman itu
 * sendiri.
 *
 * Itu bukan sekadar kerapian: pinjaman juga bisa lahir dari penjualan POS
 * "hutang" (LoanService::originateInstantly), yang piutangnya terbit di
 * cabang toko dan jurnalnya menyatu dengan jurnal penjualan di sana.
 * Memaksa angsurannya ke cabang pinjaman akan memindahkan pelunasan ke
 * unit yang tidak pernah menerbitkan piutangnya, dan membuat kedua unit
 * salah sekaligus. Pencairan aman dari masalah ini karena pinjaman POS
 * tidak pernah melewati pencairan — ia langsung aktif.
 *
 * Selama setelannya kosong, hasilnya sama persis dengan perilaku lama.
 */
class LoanBranchResolver
{
    public function __construct(private readonly CashSettingsService $cashSettings) {}

    /**
     * $bawaan = cabang yang dipakai kalau setelannya belum diisi (cabang
     * anggota saat pengajuan, atau cabang pinjaman saat pencairan dan
     * angsuran).
     */
    public function resolve(?int $bawaan): ?int
    {
        return $this->cashSettings->loanBranchId() ?? $bawaan;
    }

    /**
     * Versi yang menjamin hasilnya tidak null — untuk kolom `branch_id`
     * yang NOT NULL seperti `journal_entries` dan `loan_repayments`.
     */
    public function resolveOrFail(?int $bawaan): int
    {
        $branchId = $this->resolve($bawaan);

        if ($branchId === null) {
            throw new \RuntimeException('Transaksi pinjaman ini tidak punya cabang: pinjamannya tidak tercatat di cabang mana pun dan Cabang Transaksi Pinjaman belum diatur di Pengaturan → Kas & Cabang.');
        }

        return $branchId;
    }
}
