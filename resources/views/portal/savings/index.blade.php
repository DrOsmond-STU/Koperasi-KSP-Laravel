@extends('layouts.portal')

@section('title', 'Simpanan Saya')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .link-history { color: var(--pine-bright); font-weight: 600; text-decoration: none; font-size: 13px; margin-right: 12px; }
        .empty-note { color: var(--muted); font-size: 13px; }
    </style>

    <h2>Simpanan Saya</h2>

    <p><a href="{{ route('portal.print.savings') }}" class="link-history">Cetak Simpanan Saya</a></p>

    <table class="data-table">
        <thead>
            <tr><th>No. Rekening</th><th>Produk</th><th>Saldo</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td>{{ $account->account_number }}</td>
                    <td>{{ $account->savingsProduct->name }}</td>
                    <td>Rp {{ number_format($account->balance, 0, ',', '.') }}</td>
                    <td>{{ ucfirst($account->status) }}</td>
                    <td>
                        <a href="{{ route('portal.savings.history', $account) }}" class="link-history">Lihat Historis</a>
                        <a href="{{ route('portal.savings.deposit.create', $account) }}" class="link-history">Setor Mandiri</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><p class="empty-note">Belum ada rekening simpanan.</p></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
