@extends('layouts.app')

@section('title', 'Master Barang')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--line); display: block; }
        .thumb-placeholder { width: 40px; height: 40px; border-radius: 6px; background: var(--leaf); color: var(--pine-bright); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; }
        .link-edit { color: var(--pine-bright); font-weight: 600; text-decoration: none; font-size: 13px; }
    </style>

    <h2>Master Barang</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.master.products.create') }}" class="btn-primary">+ Tambah Barang</a>
        <a href="{{ route('admin.master.products.print-barcode') }}" target="_blank" style="margin-left:10px;">🏷 Cetak Barcode Semua Barang</a>
        <a href="{{ route('admin.master.products.export-pdf') }}" target="_blank" style="margin-left:10px;">Export PDF</a>
        <a href="{{ route('admin.master.products.export-excel') }}" style="margin-left:10px;">Export Excel</a>
    </p>

    <table class="data-table">
        <thead>
            <tr>
                <th>Foto</th><th>Kode</th><th>Nama</th><th>Kategori</th><th>Satuan</th><th>Harga Jual</th><th>Status</th><th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>
                        @if ($product->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($product->image_path) }}" alt="{{ $product->name }}" class="thumb">
                        @else
                            <div class="thumb-placeholder">{{ strtoupper(mb_substr($product->name, 0, 2)) }}</div>
                        @endif
                    </td>
                    <td>{{ $product->code }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category ?? '—' }}</td>
                    <td>{{ $product->unit }}</td>
                    <td>Rp {{ number_format((float) $product->selling_price, 0, ',', '.') }}</td>
                    <td>{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                    <td><a href="{{ route('admin.master.products.edit', $product) }}" class="link-edit">Ubah</a></td>
                </tr>
            @empty
                <tr><td colspan="8">Belum ada barang — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
