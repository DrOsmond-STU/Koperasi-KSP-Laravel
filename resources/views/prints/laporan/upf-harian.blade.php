@extends('prints.layout')

@section('title', 'Laporan Harian Retribusi UPF')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">LAPORAN HARIAN RETRIBUSI UPF</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 4px; text-align:center;">
        {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('d M Y') }}
        @if ($branch) — {{ $branch->name }} @endif
    </p>
    <p style="font-size:10pt; font-weight:700; margin:0 0 16px; text-align:center;">
        Total Pendapatan UPF Keseluruhan: Rp {{ number_format($totalAmount, 2, ',', '.') }}
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
                        <td style="text-align:right;">{{ number_format($row["retribusi_line_{$type->id}"], 2, ',', '.') }}</td>
                    @endforeach
                    <td style="text-align:right;">{{ number_format($row['total_amount'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 4 + $activeTypes->count() }}">Tidak ada transaksi retribusi UPF pada tanggal ini.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr>
                    <td colspan="3" style="text-align:right; font-weight:700;">Total</td>
                    @foreach ($activeTypes as $type)
                        <td style="text-align:right; font-weight:700;">{{ number_format($rows->sum("retribusi_line_{$type->id}"), 2, ',', '.') }}</td>
                    @endforeach
                    <td style="text-align:right; font-weight:700;">{{ number_format($rows->sum('total_amount'), 2, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Rekonsiliasi dengan Kartu Buku Besar kas: jurnal pembalik bertanggal hari
         pembatalan, jadi transaksi yang dibatalkan di hari lain membuat total
         laporan ini berbeda dari Buku Besar — rinciannya ditampilkan di sini.
         Lihat RetributionReportService::rekonsiliasiHarian(). --}}
    @php
        $rek = ($rekonsiliasi ?? []) + ['masuk' => collect(), 'batal' => collect(), 'koreksi' => collect(), 'total_masuk' => 0, 'total_batal' => 0, 'total_koreksi' => 0];
        $totalBukuBesar = round($totalAmount + $rek['total_masuk'] - $rek['total_batal'] + $rek['total_koreksi'], 2);
        $tgl = fn (string $d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y');
    @endphp
    <h3 style="font-size:10pt; margin:14px 0 6px;">Rekonsiliasi dengan Buku Besar Kas</h3>
    @if ($rek['masuk']->isEmpty() && $rek['batal']->isEmpty() && $rek['koreksi']->isEmpty())
        <p style="font-size:8.5pt; margin:0 0 10px;">
            Tidak ada pembatalan lintas hari — total laporan ini sama dengan mutasi kas retribusi di Buku Besar
            pada tanggal ini (Rp {{ number_format($totalBukuBesar, 2, ',', '.') }}, di luar setoran kas).
        </p>
    @else
        <table class="data-table" style="margin-bottom: 10px; font-size:8pt; width:auto; min-width:60%;">
            <thead>
                <tr><th>Keterangan</th><th>No. Transaksi</th><th>Pembayar</th><th>Tgl Transaksi</th><th>Tgl Batal</th><th>Alasan</th><th style="text-align:right;">Jumlah</th></tr>
            </thead>
            <tbody>
                <tr><td colspan="6" style="font-weight:700;">Total transaksi aktif (laporan di atas)</td><td style="text-align:right; font-weight:700;">{{ number_format($totalAmount, 2, ',', '.') }}</td></tr>
                @foreach ($rek['masuk'] as $r)
                    <tr>
                        <td>(+) Transaksi tanggal ini, dibatalkan di hari lain</td>
                        <td>{{ $r['transaction_number'] }}</td><td>{{ $r['payer_name'] }}</td>
                        <td>{{ $tgl($r['tanggal_transaksi']) }}</td><td>{{ $tgl($r['tanggal_batal']) }}</td><td>{{ $r['cancellation_reason'] }}</td>
                        <td style="text-align:right;">{{ number_format($r['total_amount'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                @foreach ($rek['batal'] as $r)
                    <tr>
                        <td>(-) Pembatalan hari ini atas transaksi tanggal lain</td>
                        <td>{{ $r['transaction_number'] }}</td><td>{{ $r['payer_name'] }}</td>
                        <td>{{ $tgl($r['tanggal_transaksi']) }}</td><td>{{ $tgl($r['tanggal_batal']) }}</td><td>{{ $r['cancellation_reason'] }}</td>
                        <td style="text-align:right;">({{ number_format($r['total_amount'], 2, ',', '.') }})</td>
                    </tr>
                @endforeach
                @foreach ($rek['koreksi'] as $k)
                    <tr>
                        <td>(&plusmn;) Koreksi jurnal atas transaksi retribusi</td>
                        <td>{{ $k['transaction_number'] }}</td><td>{{ $k['payer_name'] }}</td>
                        <td colspan="3">Jurnal #{{ $k['journal_id'] }} — {{ $k['description'] }}</td>
                        <td style="text-align:right;">{{ $k['amount'] < 0 ? '('.number_format(abs($k['amount']), 2, ',', '.').')' : number_format($k['amount'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="6" style="font-weight:700;">Mutasi kas retribusi menurut Buku Besar tanggal ini (di luar setoran kas)</td>
                    <td style="text-align:right; font-weight:700;">{{ number_format($totalBukuBesar, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
        <p style="font-size:7.5pt; color:#5C6E64; margin:0 0 10px;">
            Jurnal pembatalan dicatat pada tanggal pembatalan, sehingga transaksi yang dibatalkan di hari lain
            tercatat di Buku Besar pada dua tanggal berbeda. Pendapatan sebenarnya tetap angka "Total transaksi aktif".
        </p>
    @endif

    @include('prints.partials.signature-block', ['documentGroup' => 'laporan_upf'])
@endsection
