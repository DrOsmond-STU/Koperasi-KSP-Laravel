@extends('layouts.app')

@section('title', 'Kategori Produk Simpanan')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; margin-right: 10px; }
        .btn-link-danger { color: var(--brick); text-decoration: none; font-weight: 600; font-size: 13px; background: none; border: none; cursor: pointer; padding: 0; font-family: inherit; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .pill-aktif { background: var(--leaf); color: var(--pine); }
        .pill-other { background: var(--paper); color: var(--muted); }
        .aksi-cell { display: flex; align-items: center; }
        code { background: var(--paper); padding: 1px 6px; border-radius: 5px; }
    </style>

    <h2>Kategori Produk Simpanan</h2>
    <p style="color: var(--muted); margin-top: -8px;">Kelompok produk simpanan (mis. Simpanan Pokok, Wajib, Sukarela, Retribusi). Dipakai saat menambah Produk Simpanan.</p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <div class="toolbar">
        <span></span>
        <a href="{{ route('admin.master.savings-product-categories.create') }}" class="btn-primary">+ Tambah Kategori</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th>Keterangan</th><th>Produk</th><th>Status</th><th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td><code>{{ $category->code }}</code></td>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->description ?: '—' }}</td>
                    <td>{{ $productCounts[$category->code] ?? 0 }}</td>
                    <td><span class="pill {{ $category->is_active ? 'pill-aktif' : 'pill-other' }}">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="aksi-cell">
                        <a href="{{ route('admin.master.savings-product-categories.edit', $category) }}" class="btn-link">Ubah</a>
                        <form method="POST" action="{{ route('admin.master.savings-product-categories.destroy', $category) }}" onsubmit="return confirm('Hapus kategori &quot;{{ $category->name }}&quot;?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-link-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada kategori — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
