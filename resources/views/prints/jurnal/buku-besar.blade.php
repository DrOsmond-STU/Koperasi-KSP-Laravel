@extends('prints.layout')

@section('title', 'Buku Besar '.$selectedAccount->code)

@section('print-content')
    @php
        $rp = fn ($n) => number_format((float) $n, 2, ',', '.');
        $tgl = fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y');
    @endphp

    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">BUKU BESAR</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 14px; text-align:center;">
        {{ $tgl($periodStart) }} s/d {{ $tgl($periodEnd) }} — {{ $branchName }}
    </p>

    <table style="margin-bottom:12px; font-size:9pt;">
        <tr><td style="width:130px; padding:2px 0;">Akun</td><td style="padding:2px 0;">: <strong>{{ $selectedAccount->code }} — {{ $selectedAccount->name }}</strong></td></tr>
        <tr><td style="padding:2px 0;">Saldo awal periode</td><td style="padding:2px 0;">: Rp {{ $rp($ringkasan['saldo_awal']) }}</td></tr>
        <tr><td style="padding:2px 0;">Saldo akhir periode</td><td style="padding:2px 0;">: <strong>Rp {{ $rp($ringkasan['saldo_akhir']) }}</strong></td></tr>
        <tr><td style="padding:2px 0;">Jurnal dibatalkan</td><td style="padding:2px 0;">: {{ $showCancelled ? 'ditampilkan' : 'tidak ditampilkan (saldo tidak terpengaruh)' }}</td></tr>
    </table>

    @if ($dipotong)
        <p style="font-size:8.5pt; color:#A4432B; margin:0 0 10px;">
            Hanya {{ number_format($batasPdf, 0, ',', '.') }} mutasi pertama yang dicetak. Total dan saldo akhir di bawah tetap
            dihitung dari seluruh mutasi periode ini. Gunakan Ekspor Excel atau persempit periode untuk melihat semuanya.
        </p>
    @endif

    <table class="data-table" style="margin-bottom:12px; font-size:8.5pt;">
        <thead>
            <tr>
                <th style="width:62px;">Tanggal</th>
                <th style="width:48px;">No.</th>
                <th>Keterangan</th>
                <th style="text-align:right; width:88px;">Debit</th>
                <th style="text-align:right; width:88px;">Kredit</th>
                <th style="text-align:right; width:96px;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linesCetak as $line)
                @php $awal = $line['is_opening'] ?? false; @endphp
                <tr @if ($awal) style="font-weight:700; font-style:italic; background:#F4F7F4;" @endif>
                    <td>{{ \Illuminate\Support\Carbon::parse($line['date'])->format('d/m/y') }}</td>
                    <td>{{ $awal ? '' : '#'.($line['entry_id'] ?? '') }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td style="text-align:right;">{{ $awal || (float) $line['debit'] == 0 ? '' : $rp($line['debit']) }}</td>
                    <td style="text-align:right;">{{ $awal || (float) $line['credit'] == 0 ? '' : $rp($line['credit']) }}</td>
                    <td style="text-align:right;">{{ $rp($line['running_balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Tidak ada mutasi pada periode ini.</td></tr>
            @endforelse
            <tr>
                <td colspan="3" style="text-align:right; font-weight:700;">TOTAL MUTASI{{ $dipotong ? ' (SELURUH PERIODE)' : '' }}</td>
                <td style="text-align:right; font-weight:700;">{{ $rp($ringkasan['total_debit']) }}</td>
                <td style="text-align:right; font-weight:700;">{{ $rp($ringkasan['total_kredit']) }}</td>
                <td style="text-align:right; font-weight:700;">{{ $rp($ringkasan['saldo_akhir']) }}</td>
            </tr>
        </tbody>
    </table>

    @include('prints.partials.signature-block', ['documentGroup' => 'jurnal_umum'])
@endsection
