@extends('prints.layout')

@section('title', 'Cetakan Transaksi Unit Usaha')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">TRANSAKSI UNIT USAHA — {{ strtoupper($unit->name) }}</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">
        @if ($from || $to)
            {{ $from ? \Illuminate\Support\Carbon::parse($from)->translatedFormat('d M Y') : 'Awal' }}
            &ndash;
            {{ $to ? \Illuminate\Support\Carbon::parse($to)->translatedFormat('d M Y') : 'Sekarang' }}
        @else
            Seluruh Transaksi
        @endif
    </p>

    <table class="data-table" style="margin-bottom: 10px;">
        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th>Dicatat oleh</th><th style="text-align:right;">Nominal</th></tr></thead>
        <tbody>
            @forelse ($transactions as $trx)
                <tr>
                    <td>{{ $trx->created_at->translatedFormat('d M Y') }}</td>
                    <td>{{ ucfirst($trx->type) }}</td>
                    <td>{{ $trx->description ?? '-' }}</td>
                    <td>{{ $trx->createdBy->name }}</td>
                    <td style="text-align:right;">Rp {{ number_format($trx->amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Tidak ada transaksi pada rentang ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table style="margin-bottom:16px;">
        <tr><td style="width:180px; padding:3px 0;">Total Pendapatan</td><td style="padding:3px 0;">: Rp {{ number_format($transactions->where('type', 'pendapatan')->sum('amount'), 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Total Beban</td><td style="padding:3px 0;">: Rp {{ number_format($transactions->where('type', 'beban')->sum('amount'), 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0; font-weight:700;">Laba/Rugi</td><td style="padding:3px 0; font-weight:700;">: Rp {{ number_format($transactions->where('type', 'pendapatan')->sum('amount') - $transactions->where('type', 'beban')->sum('amount'), 0, ',', '.') }}</td></tr>
    </table>

    @include('prints.partials.signature-block', ['documentGroup' => 'unit_usaha'])
@endsection
