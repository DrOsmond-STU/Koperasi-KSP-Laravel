@extends('prints.layout')

@section('title', 'Cetakan Pinjaman')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px;">Cetakan Pinjaman Anggota</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 4px;">{{ $filterDescription }}</p>
    <p style="font-size:8pt; color:#5C6E64; margin:0 0 14px;">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }}</p>

    @foreach ($members as $member)
        <table class="data-table" style="margin-bottom: 16px;">
            <thead>
                <tr><th colspan="6">{{ $member->member_number }} — {{ $member->name }}</th></tr>
                <tr>
                    <th>No. Pinjaman</th>
                    <th>Produk</th>
                    <th>Plafon</th>
                    <th>Tenor</th>
                    <th>Status</th>
                    <th>Tgl Cair</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($member->loans as $loan)
                    <tr>
                        <td>{{ $loan->loan_number }}</td>
                        <td>{{ $loan->loanProduct->name }}</td>
                        <td>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                        <td>{{ $loan->tenor_months }} bulan</td>
                        <td>{{ ucfirst($loan->status) }}</td>
                        <td>{{ optional($loan->disbursed_at)->format('d/m/Y') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Belum ada pinjaman.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
@endsection
