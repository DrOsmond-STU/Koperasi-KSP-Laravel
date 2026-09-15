@extends('prints.layout')

@section('title', 'Laporan Jurnal Transaksi')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">LAPORAN JURNAL TRANSAKSI</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 14px; text-align:center;">
        {{ \Illuminate\Support\Carbon::parse($filter['date_from'])->translatedFormat('d M Y') }}
        s/d {{ \Illuminate\Support\Carbon::parse($filter['date_to'])->translatedFormat('d M Y') }}
        — {{ $branchName }}
    </p>

    <table style="margin-bottom:12px; font-size:9pt;">
        @if ($accountName)
            <tr><td style="width:120px; padding:2px 0;">Akun</td><td style="padding:2px 0;">: {{ $accountName }}</td></tr>
        @endif
        @if ($filter['source_type'])
            <tr><td style="padding:2px 0;">Jenis transaksi</td><td style="padding:2px 0;">: {{ $reportService->labelSumber($filter['source_type'] === \App\Services\Accounting\JournalReportService::SUMBER_MANUAL ? null : $filter['source_type']) }}</td></tr>
        @endif
        @if ($filter['q'])
            <tr><td style="padding:2px 0;">Pencarian</td><td style="padding:2px 0;">: "{{ $filter['q'] }}"</td></tr>
        @endif
        <tr><td style="padding:2px 0;">Jumlah transaksi</td><td style="padding:2px 0;">: {{ number_format($totals['entries'], 0, ',', '.') }}</td></tr>
    </table>

    @if ($dipotong)
        <p style="font-size:8.5pt; color:#A4432B; margin:0 0 10px;">
            Hasil dipotong pada {{ number_format($batasCetak, 0, ',', '.') }} transaksi pertama.
            Persempit rentang tanggal atau filternya untuk mencetak seluruhnya.
        </p>
    @endif

    <table class="data-table" style="margin-bottom:12px;">
        <thead>
            <tr>
                <th style="width:70px;">Tanggal</th>
                <th style="width:70px;">Kode</th>
                <th>Keterangan / Nama Akun</th>
                <th style="text-align:right; width:95px;">Debet</th>
                <th style="text-align:right; width:95px;">Kredit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td style="font-weight:700;">{{ $entry->entry_date->format('d/m/y') }}</td>
                    <td style="font-weight:700;">#{{ $entry->id }}</td>
                    <td colspan="3" style="font-weight:700;">
                        {{ $entry->description }}
                        ({{ $reportService->labelSumber($entry->source_type) }}{{ $entry->reversals->isNotEmpty() ? ', DIBATALKAN' : '' }})
                    </td>
                </tr>
                @foreach ($entry->lines as $line)
                    <tr>
                        <td></td>
                        <td>{{ $line->account?->code ?? '—' }}</td>
                        <td style="padding-left:10px;">{{ $line->account?->name ?? 'Akun terhapus' }}</td>
                        <td style="text-align:right;">{{ (float) $line->debit > 0 ? number_format((float) $line->debit, 0, ',', '.') : '' }}</td>
                        <td style="text-align:right;">{{ (float) $line->credit > 0 ? number_format((float) $line->credit, 0, ',', '.') : '' }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="5">Tidak ada transaksi pada rentang dan filter ini.</td></tr>
            @endforelse
            {{-- Total baris yang BENAR-BENAR tercetak, supaya kolomnya selalu
                 menjumlah apa yang terlihat. Kalau hasilnya dipotong, total
                 seluruh rentang ditampilkan terpisah di bawah — bukan ditukar
                 diam-diam dengan angka yang tidak bisa ditelusuri di halaman ini. --}}
            <tr>
                <td colspan="3" style="text-align:right; font-weight:700;">TOTAL {{ $dipotong ? 'YANG TERCETAK' : '' }}</td>
                <td style="text-align:right; font-weight:700;">{{ number_format((float) $entries->sum(fn ($e) => (float) $e->lines->sum('debit')), 0, ',', '.') }}</td>
                <td style="text-align:right; font-weight:700;">{{ number_format((float) $entries->sum(fn ($e) => (float) $e->lines->sum('credit')), 0, ',', '.') }}</td>
            </tr>
            @if ($dipotong)
                <tr>
                    <td colspan="3" style="text-align:right; font-weight:700;">TOTAL SELURUH RENTANG</td>
                    <td style="text-align:right; font-weight:700;">{{ number_format((float) $totals['debit'], 0, ',', '.') }}</td>
                    <td style="text-align:right; font-weight:700;">{{ number_format((float) $totals['credit'], 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    @include('prints.partials.signature-block', ['documentGroup' => 'jurnal_umum'])
@endsection
