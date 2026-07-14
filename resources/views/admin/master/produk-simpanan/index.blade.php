@extends('layouts.app')

@section('title', 'Master Produk Simpanan')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Master Produk Simpanan</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.master.savings-products.create') }}" class="btn-primary">+ Tambah Produk Simpanan</a>
    </p>

    <table class="data-table">
        <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th>Kategori</th><th>Metode Bunga</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>{{ $product->code }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ ucfirst($product->category) }}</td>
                    <td>{{ $product->interest_method }}</td>
                    <td>{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada produk simpanan — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
