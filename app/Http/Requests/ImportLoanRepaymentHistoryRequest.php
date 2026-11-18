<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportLoanRepaymentHistoryRequest extends FormRequest
{
    /** Sama seperti importir jadwal: alat migrasi, wewenangnya di bendahara. */
    public function authorize(): bool
    {
        return $this->user()?->can('saldo_awal.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Berkas riwayat jauh lebih besar dari import lain (20.874 baris,
            // ~2,1 MB), jadi batasnya dinaikkan ke 10 MB.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'mode' => ['required', Rule::in(['all_or_nothing', 'partial'])],
        ];
    }

    /**
     * Pesan `uploaded` menyebut batas server yang sebenarnya.
     *
     * Aturan itu gagal saat PHP menolak unggahan SEBELUM permintaan sampai ke
     * aplikasi — hampir selalu karena berkasnya melewati upload_max_filesize
     * milik hosting, yang bisa jauh lebih kecil daripada batas yang ditulis di
     * rules() di atas. Pesan bawaan Laravel hanya berbunyi "file gagal
     * diunggah", tanpa menyebut batas mana yang dilanggar, sehingga pengguna
     * tidak punya petunjuk apa pun untuk menindaklanjutinya.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih dulu berkas CSV riwayat pembayarannya.',
            'file.mimes' => 'Berkas harus CSV.',
            'file.max' => 'Berkas maksimal 10 MB.',
            'file.uploaded' => 'Berkas ditolak PHP sebelum sampai ke aplikasi. Batas unggah server saat ini '
                .ini_get('upload_max_filesize').' (post_max_size '.ini_get('post_max_size')
                .'), dan berkas Anda melebihi itu. Pakai berkas yang sudah dipecah menjadi beberapa bagian, '
                .'atau naikkan upload_max_filesize lewat cPanel → MultiPHP INI Editor.',
        ];
    }
}
