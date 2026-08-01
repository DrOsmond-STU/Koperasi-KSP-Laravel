@extends('layouts.app')

@section('title', 'Koreksi Persediaan')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 6px 12px; background: var(--pine); color: #fff; border: none; border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; text-decoration: none; }
        .btn-danger { padding: 6px 12px; background: transparent; color: var(--brick); border: 1px solid var(--brick); border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
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
        <thead><tr><th>Barang</th><th>Selisih</th><th>Nilai</th><th>Alasan</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($postedAdjustments as $adjustment)
                <tr>
                    <td>{{ $adjustment->product->name }}</td>
                    <td>{{ rtrim(rtrim((string) $adjustment->variance_qty, '0'), '.') }}</td>
                    <td>Rp {{ number_format((float) $adjustment->amount, 0, ',', '.') }}</td>
                    <td>{{ $adjustment->stockReason->name }}</td>
                    <td>
                        @if ($adjustment->canBeCancelledBy(auth()->user()))
                            <button type="button" class="btn-danger" data-toggle-cancel="koreksi-{{ $adjustment->id }}">Batalkan</button>
                            <form method="POST" action="{{ route('admin.persediaan.koreksi.cancel', $adjustment) }}" class="cancel-form" id="cancel-form-koreksi-{{ $adjustment->id }}" style="display:none; gap:6px; margin-top:6px;">
                                @csrf
                                <input type="text" name="reason" placeholder="Alasan" required style="width:110px; padding:5px 8px; border:1px solid var(--line); border-radius:6px; font-size:11px;">
                                <button type="submit" class="btn-danger">OK</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.persediaan.koreksi.print', $adjustment) }}" target="_blank">Cetak</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada koreksi yang diposting.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Dibatalkan</h3>
    <table class="data-table">
        <thead><tr><th>Barang</th><th>Selisih</th><th>Nilai</th><th>Alasan Pembatalan</th></tr></thead>
        <tbody>
            @forelse ($cancelledAdjustments as $adjustment)
                <tr>
                    <td>{{ $adjustment->product->name }}</td>
                    <td>{{ rtrim(rtrim((string) $adjustment->variance_qty, '0'), '.') }}</td>
                    <td>Rp {{ number_format((float) $adjustment->amount, 0, ',', '.') }}</td>
                    <td>{{ $adjustment->cancellation_reason }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada koreksi yang dibatalkan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>
        document.querySelectorAll('[data-toggle-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('cancel-form-' + btn.dataset.toggleCancel);
                form.style.display = form.style.display === 'none' ? 'flex' : 'none';
            });
        });
    </script>
@endsection
