@extends('layouts.app')

@section('title', 'Master Produk Simpanan')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; vertical-align: top; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; background: none; border: none; padding: 0; cursor: pointer; }
        .btn-danger { color: var(--brick); background: none; border: none; font-weight: 600; font-size: 13px; cursor: pointer; padding: 0; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .muted { color: var(--muted); }
        .coa { font-size: 12px; line-height: 1.5; }
        .coa .kosong { color: var(--brick); font-weight: 600; }
    </style>

    <h2>Master Produk Simpanan</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.master.savings-products.create') }}" class="btn-primary">+ Tambah Produk Simpanan</a>
    </p>

    <table class="data-table">
        <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th>Kategori</th><th>Metode Jasa</th><th>Akun COA</th><th>Status</th><th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>{{ $product->code }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ ucfirst($product->category) }}</td>
                    <td>{{ $product->interest_method }}</td>
                    <td class="coa">
                        Kewajiban:
                        @if ($product->liabilityAccount)
                            {{ $product->liabilityAccount->code }} — {{ $product->liabilityAccount->name }}
                        @else
                            <span class="kosong">belum dipetakan</span>
                        @endif
                        <br>
                        Beban jasa:
                        @if ($product->interestExpenseAccount)
                            {{ $product->interestExpenseAccount->code }} — {{ $product->interestExpenseAccount->name }}
                        @else
                            <span class="kosong">belum dipetakan</span>
                        @endif
                    </td>
                    <td>{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                    <td style="white-space: nowrap;">
                        @can('master_data.update')
                            <a href="{{ route('admin.master.savings-products.edit', $product) }}" class="btn-link">Ubah</a>
                            <form method="POST" action="{{ route('admin.master.savings-products.toggle-active', $product) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn-link">{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.master.savings-products.destroy', $product) }}" style="display:inline;"
                                  onsubmit="return confirm('Hapus produk {{ $product->code }} — {{ $product->name }}? Tindakan ini tidak bisa dibatalkan.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger">Hapus</button>
                            </form>
                        @else
                            <span class="muted">—</span>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Belum ada produk simpanan — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
