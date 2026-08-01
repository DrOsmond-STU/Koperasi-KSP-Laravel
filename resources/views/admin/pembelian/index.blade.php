@extends('layouts.app')

@section('title', 'Transaksi Pembelian')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 6px 12px; background: var(--pine); color: #fff; border: none; border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; text-decoration: none; }
        .btn-danger { padding: 6px 12px; background: transparent; color: var(--brick); border: 1px solid var(--brick); border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .pay-form { display: flex; gap: 6px; }
        .pay-form input { width: 110px; padding: 4px 8px; border: 1px solid var(--line); border-radius: 6px; }
    </style>

    <h2>Transaksi Pembelian</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.pembelian.create') }}" class="btn-primary">+ Pembelian Baru</a>
    </p>

    <h3>Menunggu Approval</h3>
    <table class="data-table">
        <thead><tr><th>No. Pembelian</th><th>Supplier</th><th>Total</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($pendingPurchases as $purchase)
                <tr>
                    <td>{{ $purchase->purchase_number }}</td>
                    <td>{{ $purchase->supplier->name }}</td>
                    <td>Rp {{ number_format((float) $purchase->total_amount, 0, ',', '.') }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.pembelian.decide', $purchase) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="decision" value="setuju">
                            <button type="submit" class="btn-primary">Setujui</button>
                        </form>
                        <form method="POST" action="{{ route('admin.pembelian.decide', $purchase) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="decision" value="tolak">
                            <button type="submit" class="btn-danger">Tolak</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">Tidak ada pembelian menunggu persetujuan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Sudah Diposting</h3>
    <table class="data-table">
        <thead><tr><th>No. Pembelian</th><th>Supplier</th><th>Total</th><th>Pembayaran</th><th>Sisa Hutang</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($postedPurchases as $purchase)
                <tr>
                    <td>{{ $purchase->purchase_number }}</td>
                    <td>{{ $purchase->supplier->name }}</td>
                    <td>Rp {{ number_format((float) $purchase->total_amount, 0, ',', '.') }}</td>
                    <td>{{ $purchase->payment_status ? ucfirst(str_replace('_', ' ', $purchase->payment_status)) : '—' }}</td>
                    <td>Rp {{ number_format((float) $purchase->remainingAmount(), 0, ',', '.') }}</td>
                    <td>
                        @if ($purchase->isKredit() && $purchase->payment_status !== 'lunas')
                            <form class="pay-form" method="POST" action="{{ route('admin.pembelian.pay', $purchase) }}">
                                @csrf
                                <input type="number" step="0.01" name="amount" placeholder="Nominal" required>
                                <button type="submit" class="btn-primary">Bayar</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.pembelian.print', $purchase) }}" target="_blank">Cetak</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada pembelian yang diposting.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
