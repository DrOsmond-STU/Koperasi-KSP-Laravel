@extends('prints.layout')

@section('title', 'Daftar Aktiva Tetap')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px;">Daftar Aktiva Tetap</h2>
    <p style="font-size:8pt; color:#5C6E64; margin:0 0 14px;">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }}</p>

    <table class="data-table">
        <thead>
            <tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Nilai Perolehan</th><th>Tanggal Perolehan</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($assets as $asset)
                <tr>
                    <td>{{ $asset->code }}</td>
                    <td>{{ $asset->name }}</td>
                    <td>{{ $asset->category->name }}</td>
                    <td>Rp {{ number_format((float) $asset->acquisition_cost, 0, ',', '.') }}</td>
                    <td>{{ $asset->acquisition_date->translatedFormat('d M Y') }}</td>
                    <td>{{ ucfirst($asset->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada aktiva tetap.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
