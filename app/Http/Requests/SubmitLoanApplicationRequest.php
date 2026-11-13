<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SubmitLoanApplicationRequest extends FormRequest
{
    /**
     * Tanggal cutoff saldo awal koperasi.
     *
     * Pinjaman yang cair pada atau sebelum tanggal ini sudah ikut terhitung
     * di saldo awal, dan penguncian saldo awal sudah membuatkan baris
     * pinjamannya sendiri (loan_number MIGRASI-*). Mencatatnya sekali lagi
     * lewat form pengajuan akan melahirkan pinjaman kembar sekaligus jurnal
     * pencairan yang menghitung ganda terhadap neraca.
     *
     * Di atas tanggal ini keadaannya berbeda: pinjaman itu memang pinjaman
     * baru yang belum terwakili di saldo awal, jadi boleh dicatat mundur dan
     * dijurnal seperti biasa ke kas, jurnal, dan buku besar.
     */
    public const CUTOFF_SALDO_AWAL = '2026-07-31';

    /**
     * Tanggal pengajuan paling awal yang diterima, yaitu sehari sesudah cutoff.
     * Dipakai validasi sekaligus atribut min pada isian tanggal di form,
     * supaya batas yang ditolak server sama persis dengan yang ditolak peramban.
     */
    public static function tanggalPalingAwal(): Carbon
    {
        return Carbon::parse(self::CUTOFF_SALDO_AWAL)->addDay()->startOfDay();
    }

    public function authorize(): bool
    {
        return $this->user()?->can('pinjaman.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', Rule::exists('members', 'id')],
            'loan_product_id' => ['required', Rule::exists('loan_products', 'id')->where('is_active', true)],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'tenor_days' => ['required', 'integer', 'min:1'],

            // Tanggal pengajuan boleh mundur supaya pinjaman yang cair sesudah
            // cutoff tapi belum sempat masuk sistem bisa dicatat apa adanya.
            // Dikosongkan berarti hari ini, sehingga pengajuan biasa di loket
            // tidak berubah sama sekali.
            //
            // bail dipasang supaya isian yang bukan tanggal berhenti di pesan
            // "bukan tanggal yang valid" saja; tanpa itu aturan after ikut
            // gagal dan staf disuguhi dua pesan sekaligus untuk satu kesalahan.
            'submitted_at' => [
                'bail',
                'nullable',
                'date',
                'after:'.self::CUTOFF_SALDO_AWAL,
                'before_or_equal:today',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'member_id' => 'anggota',
            'loan_product_id' => 'produk pinjaman',
            'principal_amount' => 'nominal pinjaman',
            'tenor_days' => 'tenor',
            'submitted_at' => 'tanggal pengajuan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $cutoff = Carbon::parse(self::CUTOFF_SALDO_AWAL)->translatedFormat('d F Y');

        return [
            'submitted_at.before_or_equal' => 'Tanggal pengajuan tidak boleh melewati hari ini.',
            'submitted_at.after' => "Tanggal pengajuan harus setelah {$cutoff}, yaitu tanggal cutoff saldo awal. "
                .'Pinjaman yang cair sampai tanggal tersebut sudah tercatat pada saldo awal, '
                .'sehingga tidak boleh diajukan ulang di sini — bila datanya keliru, perbaiki lewat menu saldo awal.',
        ];
    }
}
