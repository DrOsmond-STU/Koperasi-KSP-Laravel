@extends('layouts.app')

@section('title', 'Aktiva Tetap')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 6px 12px; background: var(--pine); color: #fff; border: none; border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; text-decoration: none; }
        .btn-danger { padding: 6px 12px; background: transparent; color: #A8472F; border: 1px solid #A8472F; border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: #A8472F; font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Aktiva Tetap</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.aktiva-tetap.create') }}" class="btn-primary">+ Aktiva Tetap Baru</a>
    </p>

    <h3>Menunggu Approval</h3>
    <table class="data-table">
        <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Nilai Perolehan</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($pendingAssets as $asset)
                <tr>
                    <td>{{ $asset->code }}</td>
                    <td>{{ $asset->name }}</td>
                    <td>{{ $asset->category->name }}</td>
                    <td>Rp {{ number_format((float) $asset->acquisition_cost, 0, ',', '.') }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.aktiva-tetap.decide', $asset) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="decision" value="setuju">
                            <button type="submit" class="btn-primary">Setujui</button>
                        </form>
                        <form method="POST" action="{{ route('admin.aktiva-tetap.decide', $asset) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="decision" value="tolak">
                            <button type="submit" class="btn-danger">Tolak</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Tidak ada aktiva tetap menunggu persetujuan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Aktif</h3>
    <table class="data-table">
        <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Nilai Perolehan</th><th>Tanggal Perolehan</th></tr></thead>
        <tbody>
            @forelse ($activeAssets as $asset)
                <tr>
                    <td>{{ $asset->code }}</td>
                    <td>{{ $asset->name }}</td>
                    <td>{{ $asset->category->name }}</td>
                    <td>Rp {{ number_format((float) $asset->acquisition_cost, 0, ',', '.') }}</td>
                    <td>{{ $asset->acquisition_date->translatedFormat('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada aktiva tetap aktif.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
