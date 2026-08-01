@extends('prints.layout')

@section('title', 'Daftar Supplier')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px;">Daftar Supplier</h2>
    <p style="font-size:8pt; color:#5C6E64; margin:0 0 14px;">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }}</p>

    <table class="data-table">
        <thead>
            <tr><th>Kode</th><th>Nama</th><th>Termin Bayar</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($suppliers as $supplier)
                <tr>
                    <td>{{ $supplier->code }}</td>
                    <td>{{ $supplier->name }}</td>
                    <td>{{ $supplier->payment_term === 'kredit' ? "Kredit {$supplier->payment_term_days} hari" : 'Tunai' }}</td>
                    <td>{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada supplier.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
