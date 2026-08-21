@extends('prints.layout')

@section('title', 'Laporan Pinjaman per Anggota')

@section('print-content')
    @php
        $m = $summary['member'];
        $totals = $summary['totals'];
        $statusLabel = ['all' => 'Semua Status', 'aktif' => 'Aktif Saja', 'lunas' => 'Lunas Saja'][$status] ?? 'Semua Status';
        $periodLabel = ($periodStart || $periodEnd)
            ? 'Cair '.($periodStart ? \Illuminate\Support\Carbon::parse($periodStart)->translatedFormat('d M Y') : '—').' s/d '.($periodEnd ? \Illuminate\Support\Carbon::parse($periodEnd)->translatedFormat('d M Y') : '—')
            : 'Seluruh periode';
    @endphp

    <h2 style="font-size:14pt; margin:0 0 2px; text-align:center; letter-spacing:0.5px;">LAPORAN PINJAMAN PER ANGGOTA</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 12px; text-align:center;">
        Filter: <strong>{{ $statusLabel }}</strong> — {{ $periodLabel }}
    </p>

    <table style="margin-bottom:12px; font-size:10pt; width:100%;">
        <tr>
            <td style="width:130px; padding:2px 0;">Nama Anggota</td>
            <td style="padding:2px 0;">: <strong>{{ $m->name }}</strong></td>
            <td style="width:150px; padding:2px 0;">No. Anggota</td>
            <td style="padding:2px 0;">: {{ $m->member_number ?? '(tanpa nomor)' }}</td>
        </tr>
    </table>

    <h3 style="font-size:11pt; margin:14px 0 6px;">Ringkasan Posisi Pinjaman</h3>
    <table class="data-table" style="margin-bottom: 14px;">
        <thead>
            <tr>
                <th style="width:110px;">No. Pinjaman</th>
                <th>Produk</th>
                <th style="width:75px;">Tgl Cair</th>
                <th style="width:60px;">Tenor</th>
                <th style="width:105px; text-align:right;">Pokok (Rp)</th>
                <th style="width:105px; text-align:right;">Terbayar (Rp)</th>
                <th style="width:105px; text-align:right;">Outstanding (Rp)</th>
                <th style="width:70px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($summary['loans'] as $row)
                <tr>
                    <td>{{ $row['loan']->loan_number }}</td>
                    <td>{{ $row['loan']->loanProduct->name }}</td>
                    <td>{{ $row['loan']->disbursed_at?->translatedFormat('d M Y') ?? '-' }}</td>
                    <td>{{ $row['loan']->tenor_days }} hari</td>
                    <td style="text-align:right;">{{ number_format($row['principal'], 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($row['paid_total'], 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($row['outstanding_total'], 0, ',', '.') }}</td>
                    <td>{{ $row['outstanding_total'] <= 0.5 ? 'Lunas' : ucfirst($row['loan']->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; color:#5C6E64;">Tidak ada pinjaman yang memenuhi filter.</td></tr>
            @endforelse
            @if ($summary['loans']->isNotEmpty())
                <tr style="background:#F4F7F4; font-weight:700;">
                    <td colspan="4" style="text-align:right;">TOTAL</td>
                    <td style="text-align:right;">{{ number_format($totals['principal'], 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($totals['paid_total'], 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($totals['outstanding_total'], 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    @if ($summary['loans']->isNotEmpty())
        <h3 style="font-size:11pt; margin:18px 0 6px; page-break-before: always;">Lampiran — Rincian Pembayaran per Pinjaman</h3>
        @foreach ($summary['loans'] as $row)
            <div style="margin-bottom:14px; @if (! $loop->first) page-break-inside: avoid; @endif">
                <p style="font-size:10pt; margin:6px 0 4px;">
                    <strong>{{ $row['loan']->loan_number }}</strong> — {{ $row['loan']->loanProduct->name }}
                    <span style="color:#5C6E64;">
                        (Pokok Rp {{ number_format($row['principal'], 0, ',', '.') }},
                        Outstanding Rp {{ number_format($row['outstanding_total'], 0, ',', '.') }})
                    </span>
                </p>
                <table class="data-table" style="font-size:9pt;">
                    <thead>
                        <tr>
                            <th style="width:75px;">Tanggal</th>
                            <th>Keterangan</th>
                            <th style="width:95px; text-align:right;">Pokok (Rp)</th>
                            <th style="width:95px; text-align:right;">Jasa (Rp)</th>
                            <th style="width:95px; text-align:right;">Jumlah (Rp)</th>
                            <th style="width:105px; text-align:right;">Sisa Setelah (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($row['repayments'] as $rp)
                            <tr>
                                <td>{{ $rp->created_at->translatedFormat('d M Y') }}</td>
                                <td>{{ $rp->description ?? '-' }}</td>
                                <td style="text-align:right;">{{ number_format((float) $rp->principal_portion, 0, ',', '.') }}</td>
                                <td style="text-align:right;">{{ number_format((float) $rp->interest_portion, 0, ',', '.') }}</td>
                                <td style="text-align:right;">{{ number_format((float) $rp->amount, 0, ',', '.') }}</td>
                                <td style="text-align:right;">{{ number_format((float) $rp->balance_after, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center; color:#5C6E64;">Belum ada pembayaran atas pinjaman ini.</td></tr>
                        @endforelse
                        @if ($row['repayments']->isNotEmpty())
                            <tr style="background:#F4F7F4; font-weight:700;">
                                <td colspan="2" style="text-align:right;">Subtotal</td>
                                <td style="text-align:right;">{{ number_format($row['paid_principal'], 0, ',', '.') }}</td>
                                <td style="text-align:right;">{{ number_format($row['paid_interest'], 0, ',', '.') }}</td>
                                <td style="text-align:right;">{{ number_format($row['paid_total'], 0, ',', '.') }}</td>
                                <td></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    @include('prints.partials.signature-block', ['documentGroup' => 'laporan'])
@endsection
