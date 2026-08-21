@extends('prints.layout')

@section('title', 'Rekap Pendapatan UPF')

@section('print-content')
    @php
        $start = \Illuminate\Support\Carbon::parse($rekap['period_start']);
        $end = \Illuminate\Support\Carbon::parse($rekap['period_end']);
        $days = $start->diffInDays($end) + 1;
        $periodLabel = $start->isSameDay($end)
            ? $start->translatedFormat('d M Y')
            : $start->translatedFormat('d M Y').' s/d '.$end->translatedFormat('d M Y');
    @endphp

    <h2 style="font-size:14pt; margin:0 0 2px; text-align:center; letter-spacing:0.5px;">REKAP PENDAPATAN UPF</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 4px; text-align:center;">
        Periode: <strong>{{ $periodLabel }}</strong>
        <span style="color:#9AA9A0;">({{ $days }} hari)</span>
    </p>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">
        {{ $branch ? 'Cabang: '.$branch->name : 'Seluruh Cabang' }}
    </p>

    {{-- Ringkasan atas: total kas masuk & jumlah transaksi (umum vs anggota). --}}
    <table style="margin-bottom:14px; font-size:10pt;">
        <tr>
            <td style="width:200px; padding:3px 0;">Periode</td>
            <td style="padding:3px 0;">: {{ $periodLabel }}</td>
        </tr>
        <tr>
            <td style="padding:3px 0;">Jumlah Transaksi</td>
            <td style="padding:3px 0;">
                : {{ number_format($rekap['transaction_count'], 0, ',', '.') }} transaksi
                <span style="color:#5C6E64;">
                    ({{ number_format($rekap['transaction_count_umum'], 0, ',', '.') }} Umum,
                    {{ number_format($rekap['transaction_count_anggota'], 0, ',', '.') }} Anggota)
                </span>
            </td>
        </tr>
        <tr>
            <td style="padding:3px 0; font-weight:700; font-size:11pt;">Total Kas Masuk</td>
            <td style="padding:3px 0; font-weight:700; font-size:11pt; color:#11543B;">
                : Rp {{ number_format($rekap['total_cash_in'], 0, ',', '.') }}
            </td>
        </tr>
    </table>

    {{-- Rekap per jenis retribusi. --}}
    <h3 style="font-size:11pt; margin:14px 0 6px;">Rekap per Jenis Retribusi</h3>
    <table class="data-table" style="margin-bottom: 10px;">
        <thead>
            <tr>
                <th style="width:32px; text-align:center;">No</th>
                <th>Jenis Retribusi</th>
                <th style="width:80px; text-align:right;">Baris</th>
                <th style="width:130px; text-align:right;">Total (Rp)</th>
            </tr>
        </thead>
        <tbody>
            {{-- Daftar SEMUA jenis retribusi aktif (dijamin oleh
                 RetributionReportService::rekapPendapatan) — jenis yang
                 tidak bertransaksi tetap tampil dengan angka nol supaya
                 pengurus melihat cakupan lengkap tarif yang berlaku. --}}
            @php $breakdownTotal = 0; @endphp
            @forelse ($rekap['breakdown'] as $i => $row)
                @php $breakdownTotal += $row['total']; @endphp
                <tr @if ($row['count'] === 0) style="color:#5C6E64;" @endif>
                    <td style="text-align:center;">{{ $i + 1 }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td style="text-align:right;">{{ number_format($row['count'], 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($row['total'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center; color:#5C6E64;">
                        Belum ada jenis retribusi aktif. Silakan tambahkan
                        di menu Pengaturan &raquo; Jenis Retribusi.
                    </td>
                </tr>
            @endforelse
            @if (! empty($rekap['breakdown']))
                <tr style="background:#F4F7F4;">
                    <td colspan="2" style="text-align:right; font-weight:700;">Total</td>
                    <td style="text-align:right; font-weight:700;">
                        {{ number_format(collect($rekap['breakdown'])->sum('count'), 0, ',', '.') }}
                    </td>
                    <td style="text-align:right; font-weight:700;">
                        {{ number_format($breakdownTotal, 0, ',', '.') }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Ringkasan bawah: total kas masuk (ulang, sebagai penutup). --}}
    <table style="margin-top:14px; font-size:10pt;">
        <tr>
            <td style="width:200px; padding:3px 0; font-weight:700;">TOTAL KAS MASUK</td>
            <td style="padding:3px 0; font-weight:700; color:#11543B; font-size:11pt;">
                : Rp {{ number_format($rekap['total_cash_in'], 0, ',', '.') }}
            </td>
        </tr>
        <tr>
            <td style="padding:3px 0; font-size:8pt; color:#5C6E64;">Akun Kas Lawan</td>
            <td style="padding:3px 0; font-size:8pt; color:#5C6E64;">: 1101600 — KAS AO RIDWAN (UPF)</td>
        </tr>
    </table>

    <p style="font-size:8pt; color:#9AA9A0; margin-top:18px;">
        Catatan: rekap ini menghitung total kas masuk dari transaksi retribusi UPF pada periode di
        atas — tidak termasuk transaksi yang dibatalkan. Nilai per jenis retribusi diambil dari
        snapshot baris <em>retribution_transaction_lines</em>, konsisten dengan yang diposting ke
        jurnal besar via <em>JournalEngine</em>.
    </p>

    @include('prints.partials.signature-block', ['documentGroup' => 'laporan_upf'])
@endsection
