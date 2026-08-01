@extends('layouts.portal')

@section('title', 'Historis Simpanan')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .type-tag { font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 10px; }
        .type-tag.setor { background: var(--leaf); color: var(--pine-bright); }
        .type-tag.tarik { background: var(--brick-soft); color: var(--brick); }
        .empty-note { color: var(--muted); font-size: 13px; }
        .link-back { color: var(--pine-bright); font-size: 13px; text-decoration: none; }
    </style>

    <p><a href="{{ route('portal.savings.index') }}" class="link-back">&larr; Kembali ke Simpanan Saya</a></p>
    <h2>Historis Simpanan — {{ $account->account_number }}</h2>
    <p style="color:var(--muted); font-size:13px; margin-top:-8px;">
        {{ $account->savingsProduct->name }} — Saldo saat ini: Rp {{ number_format($account->balance, 0, ',', '.') }}
    </p>

    <div class="panel">
        <table class="data-table">
            <thead>
                <tr><th>Tanggal</th><th>Jenis</th><th>Nominal</th><th>Saldo Setelah</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($transactions as $trx)
                    <tr>
                        <td>{{ $trx->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td><span class="type-tag {{ $trx->type }}">{{ ucfirst($trx->type) }}</span></td>
                        <td>Rp {{ number_format($trx->amount, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($trx->balance_after, 0, ',', '.') }}</td>
                        <td>{{ $trx->isCancelled() ? 'Dibatalkan' : 'Berhasil' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><p class="empty-note">Belum ada transaksi pada rekening ini.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
