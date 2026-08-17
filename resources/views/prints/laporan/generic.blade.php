@extends('prints.layout')

@section('title', $title)

@section('print-content')
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #D7E2DB; padding: 5px 8px; text-align: left; }
        th { background: #F4F7F4; }
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

    <table>
        <thead>
            <tr>
                @foreach ($columns as $label)
                    <th>{{ $label }}</th>
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
                        <td>{{ $row[$key] ?? '-' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
