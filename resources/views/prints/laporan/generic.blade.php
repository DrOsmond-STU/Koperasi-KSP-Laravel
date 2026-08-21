@extends('prints.layout')

@section('title', $title)

@section('print-content')
    <style>
        {{-- Tabel dicetak lebih kecil dari teks lain (bawaan 11pt): laporan
             keuangan berkolom banyak jadi terlalu rapat kalau seukuran teks
             biasa, dan barisnya membengkak ke halaman berikutnya. --}}
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9pt; }
        th, td { border: 1px solid #D7E2DB; padding: 3px 6px; text-align: left; }
        th { background: #F4F7F4; }
        {{-- Kolom nominal dirata-kanan supaya digit satuannya sejajar antar
             baris; angka rupiah yang rata kiri hampir mustahil dibandingkan
             sekilas, apalagi saat panjang digitnya berbeda-beda. --}}
        th.angka, td.angka { text-align: right; }
        {{-- Baris sub total & total diberi latar pembeda: pada laporan sepanjang
             ribuan baris, angka rekapnya harus bisa ditemukan tanpa dibaca satu
             per satu. Warnanya sengaja pekat karena banyak koperasi mencetak
             hitam-putih, dan bedanya harus tetap terlihat sebagai beda abu. --}}
        tr.baris-ringkasan td { background: #E3EDE6; font-weight: 700; }
        tr.baris-judul td { background: #11543B; color: #FFFFFF; font-weight: 700; }
    </style>

    <h2 style="font-size:13pt; margin:0 0 2px;">{{ $title }}</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 2px;">Dicetak {{ $generatedAt->translatedFormat('d M Y H:i') }} — {{ $rows->count() }} baris</p>

    {{-- Saringan cetak harus terbaca di kertasnya sendiri: pembaca laporan
         keuangan tidak boleh menebak-nebak kenapa jumlah barisnya beda dengan
         yang di layar. --}}
    @if (! empty($catatan ?? null))
        <p style="font-size:8.5pt; color:#8A5A2B; margin:0;">{{ $catatan }}</p>
    @endif

    @php
        // Baris laporan sudah berupa teks terformat, jadi kolom nominal
        // dikenali dari bentuk isinya, bukan dari tipe data. Awalan "Rp" wajib
        // ikut diterima: hampir semua kolom uang melewati LaporanController::
        // rupiah() yang menempelkannya, dan tanpa ini tidak ada satu pun kolom
        // uang yang lolos deteksi. Tanda kurung dipakai untuk angka negatif.
        $isAngka = fn ($v) => is_string($v)
            && preg_match('/^\(?-?\s*(Rp\s*)?-?[\d., ]+\)?$/i', trim($v)) === 1
            && preg_match('/\d/', $v) === 1;

        // Perataan ditentukan per kolom, bukan per sel: kalau ditentukan per
        // sel, baris judul dan sub total yang selnya kosong akan membuat
        // kolomnya melompat-lompat antara kiri dan kanan.
        $kolomAngka = [];
        foreach (array_keys($columns) as $key) {
            $kolomAngka[$key] = $rows->contains(fn ($row) => $isAngka($row[$key] ?? null));
        }
    @endphp

    <table>
        <thead>
            <tr>
                @foreach ($columns as $key => $label)
                    <th @class(['angka' => $kolomAngka[$key] ?? false])>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr @class([
                    'baris-ringkasan' => ($row['_gaya'] ?? null) === 'ringkasan',
                    'baris-judul' => ($row['_gaya'] ?? null) === 'judul',
                ])>
                    @foreach (array_keys($columns) as $key)
                        <td @class(['angka' => $kolomAngka[$key] ?? false])>{{ $row[$key] ?? '-' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
