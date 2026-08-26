@extends('prints.layout')

@section('title', 'Laporan Harian Retribusi UPF')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">LAPORAN HARIAN RETRIBUSI UPF</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 4px; text-align:center;">
        {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('d M Y') }}
        @if ($branch) — {{ $branch->name }} @endif
    </p>
    <p style="font-size:10pt; font-weight:700; margin:0 0 16px; text-align:center;">
        Total Pendapatan UPF Keseluruhan: Rp {{ number_format($totalAmount, 0, ',', '.') }}
    </p>

    <table class="data-table" style="margin-bottom: 10px; font-size:8pt;">
        <thead>
            <tr>
                <th>No. Transaksi</th>
                <th>Pembayar</th>
                <th>Metode</th>
                @foreach ($activeTypes as $type)
                    <th style="text-align:right;">{{ $type->name }}</th>
                @endforeach
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['transaction_number'] }}</td>
                    <td>{{ $row['payer_name'] }}</td>
                    <td>{{ ucfirst($row['payment_method']) }}</td>
                    @foreach ($activeTypes as $type)
                        <td style="text-align:right;">{{ number_format($row["retribusi_line_{$type->id}"], 0, ',', '.') }}</td>
                    @endforeach
                    <td style="text-align:right;">{{ number_format($row['total_amount'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 4 + $activeTypes->count() }}">Tidak ada transaksi retribusi UPF pada tanggal ini.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr>
                    <td colspan="3" style="text-align:right; font-weight:700;">Total</td>
                    @foreach ($activeTypes as $type)
                        <td style="text-align:right; font-weight:700;">{{ number_format($rows->sum("retribusi_line_{$type->id}"), 0, ',', '.') }}</td>
                    @endforeach
                    <td style="text-align:right; font-weight:700;">{{ number_format($rows->sum('total_amount'), 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    @include('prints.partials.signature-block', ['documentGroup' => 'laporan_upf'])
@endsection
