@extends('layouts.app')

@section('title', 'Koreksi Persediaan')

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

    <h2>Koreksi Persediaan (Stock Opname)</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.persediaan.koreksi.create') }}" class="btn-primary">+ Koreksi Baru</a>
    </p>

    <h3>Menunggu Approval (Selisih Minus)</h3>
    <table class="data-table">
        <thead><tr><th>Barang</th><th>Stok Sistem</th><th>Stok Fisik</th><th>Selisih</th><th>Alasan</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($pendingAdjustments as $adjustment)
                <tr>
                    <td>{{ $adjustment->product->name }}</td>
                    <td>{{ rtrim(rtrim((string) $adjustment->system_qty, '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim((string) $adjustment->physical_qty, '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim((string) $adjustment->variance_qty, '0'), '.') }}</td>
                    <td>{{ $adjustment->stockReason->name }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.persediaan.koreksi.decide', $adjustment) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="decision" value="setuju">
                            <button type="submit" class="btn-primary">Setujui</button>
                        </form>
                        <form method="POST" action="{{ route('admin.persediaan.koreksi.decide', $adjustment) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="decision" value="tolak">
                            <button type="submit" class="btn-danger">Tolak</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Tidak ada koreksi menunggu persetujuan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Sudah Diposting</h3>
    <table class="data-table">
        <thead><tr><th>Barang</th><th>Selisih</th><th>Nilai</th><th>Alasan</th></tr></thead>
        <tbody>
            @forelse ($postedAdjustments as $adjustment)
                <tr>
                    <td>{{ $adjustment->product->name }}</td>
                    <td>{{ rtrim(rtrim((string) $adjustment->variance_qty, '0'), '.') }}</td>
                    <td>Rp {{ number_format((float) $adjustment->amount, 0, ',', '.') }}</td>
                    <td>{{ $adjustment->stockReason->name }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada koreksi yang diposting.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
