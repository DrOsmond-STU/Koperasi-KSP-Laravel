@extends('prints.layout')

@section('title', 'Cetakan Simpanan')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px;">Cetakan Simpanan Anggota</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 4px;">{{ $filterDescription }}</p>
    <p style="font-size:8pt; color:#5C6E64; margin:0 0 14px;">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }}</p>

    @foreach ($members as $member)
        <table class="data-table" style="margin-bottom: 4px;">
            <thead>
                <tr><th colspan="4">{{ $member->member_number }} — {{ $member->name }}</th></tr>
                <tr>
                    <th>No. Rekening</th>
                    <th>Produk</th>
                    <th>Status</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($member->savingsAccounts as $account)
                    <tr>
                        <td>{{ $account->account_number }}</td>
                        <td>{{ $account->savingsProduct->name }}</td>
                        <td>{{ ucfirst($account->status) }}</td>
                        <td>Rp {{ number_format($account->balance, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada rekening simpanan.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p style="font-size:9pt; margin:0 0 2px; font-weight:600;">
            Total Simpanan — Seluruh Rekening: Rp {{ number_format($member->savingsAccounts->sum('balance'), 0, ',', '.') }}
        </p>
        <p style="font-size:9pt; margin:0 0 16px; font-weight:600;">
            Total Sudah Dibayar (Diangsur) — Seluruh Pinjaman: Rp {{ number_format($member->total_diangsur, 0, ',', '.') }}
        </p>
    @endforeach
@endsection
