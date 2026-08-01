@extends('prints.layout')

@section('title', 'Daftar Barang')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px;">Daftar Barang</h2>
    <p style="font-size:8pt; color:#5C6E64; margin:0 0 14px;">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }}</p>

    <table class="data-table">
        <thead>
            <tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Satuan</th><th>Harga Jual</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>{{ $product->code }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category ?? '—' }}</td>
                    <td>{{ $product->unit }}</td>
                    <td>Rp {{ number_format((float) $product->selling_price, 0, ',', '.') }}</td>
                    <td>{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada barang.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
