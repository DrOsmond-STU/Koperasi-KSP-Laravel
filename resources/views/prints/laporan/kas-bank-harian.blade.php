@extends('prints.layout')

@section('title', 'Laporan Harian Kas/Bank')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">LAPORAN HARIAN KAS/BANK</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">
        {{ \Illuminate\Support\Carbon::parse($report['date'])->translatedFormat('d M Y') }}
        @if ($branch) — {{ $branch->name }} @endif
    </p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:180px; padding:3px 0;">Saldo Awal</td><td style="padding:3px 0;">: Rp {{ number_format($report['opening_balance'], 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Total Kas Masuk</td><td style="padding:3px 0;">: Rp {{ number_format($report['total_in'], 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Total Kas Keluar</td><td style="padding:3px 0;">: Rp {{ number_format($report['total_out'], 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0; font-weight:700;">Saldo Akhir</td><td style="padding:3px 0; font-weight:700;">: Rp {{ number_format($report['closing_balance'], 0, ',', '.') }}</td></tr>
    </table>

    <table class="data-table" style="margin-bottom: 10px;">
        <thead><tr><th>Keterangan</th><th>Akun</th><th>Diinput oleh</th><th style="text-align:right;">Masuk</th><th style="text-align:right;">Keluar</th></tr></thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ $row['account_name'] }}</td>
                    <td>{{ $row['created_by_name'] }}</td>
                    <td style="text-align:right;">{{ $row['debit'] > 0 ? number_format($row['debit'], 0, ',', '.') : '-' }}</td>
                    <td style="text-align:right;">{{ $row['credit'] > 0 ? number_format($row['credit'], 0, ',', '.') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Tidak ada transaksi kas/bank pada tanggal ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('prints.partials.signature-block', ['documentGroup' => 'laporan_kas_bank'])
@endsection
