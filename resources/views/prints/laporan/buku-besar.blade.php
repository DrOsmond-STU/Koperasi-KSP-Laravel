@extends('prints.layout')

@section('title', 'Kartu Buku Besar')

@section('print-content')
    @php
        $start = \Illuminate\Support\Carbon::parse($periodStart);
        $end = \Illuminate\Support\Carbon::parse($periodEnd);
        $periodLabel = $start->translatedFormat('d M Y').' s/d '.$end->translatedFormat('d M Y');
        $totalDebit = collect($lines)->sum(fn ($l) => (float) $l['debit']);
        $totalCredit = collect($lines)->sum(fn ($l) => (float) $l['credit']);
        $closingBalance = $lines->isNotEmpty() ? (float) $lines->last()['running_balance'] : (float) $openingBalance;
    @endphp

    <h2 style="font-size:14pt; margin:0 0 2px; text-align:center; letter-spacing:0.5px;">KARTU BUKU BESAR</h2>
    <p style="font-size:10pt; margin:0 0 4px; text-align:center;">
        <strong>{{ $account->code }} — {{ $account->name }}</strong>
        <span style="color:#5C6E64;">({{ $account->normal_balance === 'DEBIT' ? 'Saldo Normal Debit' : 'Saldo Normal Kredit' }})</span>
    </p>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 4px; text-align:center;">
        Periode: <strong>{{ $periodLabel }}</strong>
    </p>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 14px; text-align:center;">
        {{ $isConsolidated ? 'Konsolidasi Seluruh Cabang' : 'Cabang: '.$branchName }}
    </p>

    <table class="data-table" style="margin-bottom: 10px;">
        <thead>
            <tr>
                <th style="width:80px;">Tanggal</th>
                <th>Keterangan</th>
                <th style="width:100px; text-align:right;">Debit (Rp)</th>
                <th style="width:100px; text-align:right;">Kredit (Rp)</th>
                <th style="width:110px; text-align:right;">Saldo Berjalan (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background:#F4F7F4; font-weight:700;">
                <td colspan="4" style="text-align:right;">Saldo Awal Periode</td>
                <td style="text-align:right;">{{ number_format((float) $openingBalance, 0, ',', '.') }}</td>
            </tr>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($line['date'])->translatedFormat('d M Y') }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td style="text-align:right;">{{ (float) $line['debit'] > 0 ? number_format((float) $line['debit'], 0, ',', '.') : '' }}</td>
                    <td style="text-align:right;">{{ (float) $line['credit'] > 0 ? number_format((float) $line['credit'], 0, ',', '.') : '' }}</td>
                    <td style="text-align:right;">{{ number_format((float) $line['running_balance'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center; color:#5C6E64;">
                        Tidak ada mutasi pada periode ini.
                    </td>
                </tr>
            @endforelse
            <tr style="background:#F4F7F4; font-weight:700;">
                <td colspan="2" style="text-align:right;">Total Mutasi Periode</td>
                <td style="text-align:right;">{{ number_format($totalDebit, 0, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format($totalCredit, 0, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format($closingBalance, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <p style="font-size:8pt; color:#9AA9A0; margin-top:14px;">
        Catatan: saldo berjalan dihitung sesuai sifat akun ({{ $account->normal_balance === 'DEBIT' ? 'debit menambah, kredit mengurangi' : 'kredit menambah, debit mengurangi' }}).
        Sumber data: journal_lines yang sudah diposting; angka identik dengan tampilan Buku Besar interaktif.
    </p>

    @include('prints.partials.signature-block', ['documentGroup' => 'laporan_keuangan'])
@endsection
