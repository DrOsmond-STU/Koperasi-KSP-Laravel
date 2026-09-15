<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sisipkan akun kas khusus Petugas UPF (AO Ridwan) — dipakai
     * RetributionService sebagai default jurnal LAWAN (debit) untuk
     * transaksi retribusi UPF (umum & anggota). Idempotent: aman
     * dijalankan berulang, dan tidak mengganggu DB yang sudah punya
     * baris ini dari seeder ChartOfAccountsSeeder.
     */
    public function up(): void
    {
        $existing = DB::table('chart_of_accounts')->where('code', '1101600')->first();

        if ($existing !== null) {
            return;
        }

        $parentExists = DB::table('chart_of_accounts')->where('code', '1101')->exists();

        DB::table('chart_of_accounts')->insert([
            'code' => '1101600',
            'name' => 'KAS AO RIDWAN (UPF)',
            'type' => 'ASET',
            'group' => 'Aset Lancar',
            'normal_balance' => 'DEBIT',
            'is_postable' => true,
            'parent_code' => $parentExists ? '1101' : null,
            'statement' => 'NERACA',
            'notes' => 'v2 - Kas Petugas UPF (AO Ridwan) - default jurnal lawan (debit) untuk transaksi Retribusi UPF (umum & anggota)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('chart_of_accounts')->where('code', '1101600')->delete();
    }
};
