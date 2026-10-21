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
                : Rp {{ number_format($rekap['total_cash_in'], 2, ',', '.') }}
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
            {{-- RetributionReportService::rekapPendapatan() mengembalikan
                 SEMUA jenis retribusi aktif (termasuk yang nol, count=0)
                 supaya datanya tetap lengkap untuk pemakaian lain. Di
                 cetakan ini baris nol disaring keluar — permintaan: hanya
                 jenis yang benar-benar ada nilainya yang ditampilkan. --}}
            @php
                $breakdownRows = collect($rekap['breakdown'])->filter(fn ($row) => (float) $row['total'] > 0)->values();
                $breakdownTotal = 0;
            @endphp
            @forelse ($breakdownRows as $i => $row)
                @php $breakdownTotal += $row['total']; @endphp
                <tr>
                    <td style="text-align:center;">{{ $i + 1 }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td style="text-align:right;">{{ number_format($row['count'], 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($row['total'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center; color:#5C6E64;">
                        @if (empty($rekap['breakdown']))
                            Belum ada jenis retribusi aktif. Silakan tambahkan
                            di menu Pengaturan &raquo; Jenis Retribusi.
                        @else
                            Tidak ada transaksi retribusi pada periode ini.
                        @endif
                    </td>
                </tr>
            @endforelse
            @if ($breakdownRows->isNotEmpty())
                <tr style="background:#F4F7F4;">
                    <td colspan="2" style="text-align:right; font-weight:700;">Total</td>
                    <td style="text-align:right; font-weight:700;">
                        {{ number_format($breakdownRows->sum('count'), 0, ',', '.') }}
                    </td>
                    <td style="text-align:right; font-weight:700;">
                        {{ number_format($breakdownTotal, 2, ',', '.') }}
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
                : Rp {{ number_format($rekap['total_cash_in'], 2, ',', '.') }}
            </td>
        </tr>
        <tr>
            <td style="padding:3px 0; font-size:8pt; color:#5C6E64;">Akun Kas Lawan</td>
            <td style="padding:3px 0; font-size:8pt; color:#5C6E64;">
                : @if ($cashAccount) {{ $cashAccount->code }} — {{ $cashAccount->name }} @else — @endif
            </td>
        </tr>
    </table>

    {{-- Rekonsiliasi dengan Kartu Buku Besar kas untuk periode ini: jurnal
         pembalik bertanggal hari pembatalan, jadi pembatalan yang melewati
         batas periode membuat total rekap berbeda dari Buku Besar.
         Lihat RetributionReportService::rekonsiliasiPeriode(). --}}
    @php
        $rek = ($rekonsiliasi ?? []) + ['masuk' => collect(), 'batal' => collect(), 'koreksi' => collect(), 'total_masuk' => 0, 'total_batal' => 0, 'total_koreksi' => 0];
        $totalBukuBesar = round($rekap['total_cash_in'] + $rek['total_masuk'] - $rek['total_batal'] + $rek['total_koreksi'], 2);
        $tgl = fn (string $d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y');
    @endphp
    <h3 style="font-size:11pt; margin:16px 0 6px;">Rekonsiliasi dengan Buku Besar Kas</h3>
    @if ($rek['masuk']->isEmpty() && $rek['batal']->isEmpty() && $rek['koreksi']->isEmpty())
        <p style="font-size:9pt; margin:0 0 10px;">
            Tidak ada pembatalan yang melewati batas periode — total kas masuk rekap ini sama dengan mutasi kas
            retribusi di Buku Besar pada periode ini (Rp {{ number_format($totalBukuBesar, 2, ',', '.') }}, di luar setoran kas).
        </p>
    @else
        <table class="data-table" style="margin-bottom: 8px; font-size:8pt;">
            <thead>
                <tr><th>Keterangan</th><th>No. Transaksi</th><th>Tgl Transaksi</th><th>Tgl Batal</th><th style="width:95px; text-align:right;">Jumlah (Rp)</th></tr>
            </thead>
            <tbody>
                <tr><td colspan="4" style="font-weight:700;">Total kas masuk (rekap di atas)</td><td style="text-align:right; font-weight:700;">{{ number_format($rekap['total_cash_in'], 2, ',', '.') }}</td></tr>
                @foreach ($rek['masuk'] as $r)
                    <tr>
                        <td>(+) Transaksi periode ini, dibatalkan setelah periode</td>
                        <td>{{ $r['transaction_number'] }}<br><span style="color:#5C6E64;">{{ $r['payer_name'] }}</span></td>
                        <td>{{ $tgl($r['tanggal_transaksi']) }}</td><td>{{ $tgl($r['tanggal_batal']) }}</td>
                        <td style="text-align:right;">{{ number_format($r['total_amount'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                @foreach ($rek['batal'] as $r)
                    <tr>
                        <td>(-) Pembatalan dalam periode atas transaksi sebelum periode</td>
                        <td>{{ $r['transaction_number'] }}<br><span style="color:#5C6E64;">{{ $r['payer_name'] }}</span></td>
                        <td>{{ $tgl($r['tanggal_transaksi']) }}</td><td>{{ $tgl($r['tanggal_batal']) }}</td>
                        <td style="text-align:right;">({{ number_format($r['total_amount'], 2, ',', '.') }})</td>
                    </tr>
                @endforeach
                @foreach ($rek['koreksi'] as $k)
                    <tr>
                        <td>(&plusmn;) Koreksi jurnal atas transaksi retribusi</td>
                        <td>{{ $k['transaction_number'] }}<br><span style="color:#5C6E64;">{{ $k['payer_name'] }}</span></td>
                        <td colspan="2">Jurnal #{{ $k['journal_id'] }} — {{ $k['description'] }}</td>
                        <td style="text-align:right;">{{ $k['amount'] < 0 ? '('.number_format(abs($k['amount']), 2, ',', '.').')' : number_format($k['amount'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr style="background:#F4F7F4;">
                    <td colspan="4" style="font-weight:700;">Mutasi kas retribusi menurut Buku Besar periode ini (di luar setoran kas)</td>
                    <td style="text-align:right; font-weight:700;">{{ number_format($totalBukuBesar, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
        <p style="font-size:7.5pt; color:#5C6E64; margin:0 0 10px;">
            Jurnal pembatalan dicatat pada tanggal pembatalan. Pembatalan yang terjadi di dalam periode yang sama
            saling meniadakan; yang melewati batas periode ditampilkan di atas. Pendapatan sebenarnya tetap angka
            "Total kas masuk".
        </p>
    @endif

    <p style="font-size:8pt; color:#9AA9A0; margin-top:18px;">
        Catatan: rekap ini menghitung total kas masuk dari transaksi retribusi UPF pada periode di
        atas — tidak termasuk transaksi yang dibatalkan. Nilai per jenis retribusi diambil dari
        snapshot baris <em>retribution_transaction_lines</em>, konsisten dengan yang diposting ke
        jurnal besar via <em>JournalEngine</em>.
    </p>

    @include('prints.partials.signature-block', ['documentGroup' => 'laporan_upf'])
@endsection
