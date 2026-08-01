<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Configurable signature lines per printable document group. Slots for
     * signers who ARE determined by real data (the loan applicant, the
     * journal entry's preparer, the teller who processed a transaction)
     * are rendered directly by each print view from that data — this table
     * only holds the STATIC/ceremonial sign-off lines (Pengurus, Ketua
     * Koperasi, ...) that admin configures, since those roles aren't
     * captured by any existing approval workflow.
     */
    public function up(): void
    {
        Schema::create('document_signature_slots', function (Blueprint $table) {
            $table->id();
            $table->enum('document_group', [
                'pengajuan_pinjaman',
                'pengajuan_penarikan',
                'kas_keluar',
                'kas_masuk',
                'jurnal_umum',
                'dokumen_gudang',
                'aktiva_tetap',
                'laporan_kas_bank',
                'laporan_upf',
                'unit_usaha',
            ]);
            $table->unsignedSmallInteger('slot_order')->default(0);
            $table->string('label');
            $table->foreignId('document_signatory_id')->nullable()->constrained('document_signatories')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_group', 'slot_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_signature_slots');
    }
};
