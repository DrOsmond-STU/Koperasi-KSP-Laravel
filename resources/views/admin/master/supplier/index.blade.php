@extends('layouts.app')

@section('title', 'Master Supplier')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Master Supplier</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.master.suppliers.create') }}" class="btn-primary">+ Tambah Supplier</a>
        <a href="{{ route('admin.master.suppliers.export-pdf') }}" target="_blank" style="margin-left:10px;">Export PDF</a>
        <a href="{{ route('admin.master.suppliers.export-excel') }}" style="margin-left:10px;">Export Excel</a>
    </p>

    <table class="data-table">
        <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th>Termin Bayar</th><th>Status</th>
            </tr>
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
                <tr><td colspan="4">Belum ada supplier — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
