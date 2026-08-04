@extends('layouts.app')

@section('title', 'Cabang')

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
        .muted { color: var(--muted); }
    </style>

    <h2>Cabang</h2>
    <p class="muted" style="margin-top: -8px;">
        Kelola kantor pusat dan cabang koperasi. Cabang dipakai untuk membatasi
        data yang bisa dilihat tiap pengguna (Cabang Scope di Manajemen Pengguna)
        dan menjadi penanda asal setiap transaksi.
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <div class="toolbar">
        <span></span>
        @can('master_data.create')
            <a href="{{ route('admin.master.branches.create') }}" class="btn-primary">+ Tambah Cabang</a>
        @endcan
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th>Cabang Induk</th><th>Alamat</th><th>Tanggal Operasional</th><th>Status</th><th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($branches as $branch)
                <tr>
                    <td>{{ $branch->code }}</td>
                    <td>{{ $branch->name }}</td>
                    <td>{{ $branch->parentBranch?->name ?? '—' }}</td>
                    <td>{{ $branch->address ?: '—' }}</td>
                    <td>{{ $branch->operational_date?->translatedFormat('d M Y') ?? '—' }}</td>
                    <td><span class="pill {{ $branch->is_active ? 'pill-aktif' : 'pill-other' }}">{{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="aksi-cell">
                        <a href="{{ route('admin.master.branches.edit', $branch) }}" class="btn-link">Ubah</a>
                        @can('master_data.update')
                            <form method="POST" action="{{ route('admin.master.branches.destroy', $branch) }}" onsubmit="return confirm('Hapus cabang &quot;{{ $branch->name }}&quot;? Cabang yang sudah punya transaksi tidak bisa dihapus — nonaktifkan saja.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-link-danger">Hapus</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Belum ada cabang — tambahkan Kantor Pusat lebih dulu.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
