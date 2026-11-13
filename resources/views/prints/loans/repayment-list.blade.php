@extends('prints.layout')

@section('title', 'Daftar Angsuran Anggota')

@section('print-content')
    @php
        $rp = fn ($n) => number_format((float) $n, 2, ',', '.');
        $tgl = fn (?string $d) => $d ? \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y') : null;
        $periode = match (true) {
            $filters['date_from'] && $filters['date_to'] => $tgl($filters['date_from']).' s/d '.$tgl($filters['date_to']),
            (bool) $filters['date_from'] => 'Sejak '.$tgl($filters['date_from']),
            (bool) $filters['date_to'] => 'Sampai '.$tgl($filters['date_to']),
            default => 'Semua tanggal',
        };
        $tercetakAktif = $repayments->reject->isCancelled();
    @endphp

    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">DAFTAR ANGSURAN ANGGOTA</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 12px; text-align:center;">Periode tanggal bayar: {{ $periode }}</p>

    <table style="margin-bottom:10px; font-size:9pt;">
        <tr><td style="width:130px; padding:2px 0;">Anggota</td><td style="padding:2px 0;">: {{ $memberLabel ?? 'Semua anggota' }}</td></tr>
        @if ($filters['q'])
            <tr><td style="padding:2px 0;">Pencarian</td><td style="padding:2px 0;">: "{{ $filters['q'] }}"</td></tr>
        @endif
        <tr><td style="padding:2px 0;">Status</td><td style="padding:2px 0;">: {{ match ($filters['status']) { 'aktif' => 'Aktif saja', 'dibatalkan' => 'Dibatalkan saja', default => 'Semua (yang dibatalkan tidak dijumlah)' } }}</td></tr>
        <tr><td style="padding:2px 0;">Jumlah angsuran</td><td style="padding:2px 0;">: {{ number_format($totals['jumlah'], 0, ',', '.') }} aktif{{ $totals['jumlah_batal'] > 0 ? ', '.number_format($totals['jumlah_batal'], 0, ',', '.').' dibatalkan' : '' }}</td></tr>
        <tr><td style="padding:2px 0;">Dicetak</td><td style="padding:2px 0;">: {{ now()->translatedFormat('d M Y H:i') }} oleh {{ $dicetakOleh }}</td></tr>
    </table>

    @if ($dipotong)
        <p style="font-size:8.5pt; color:#A4432B; margin:0 0 10px;">
            Hasil dipotong pada {{ number_format($batasCetak, 0, ',', '.') }} angsuran pertama.
            Persempit periode atau filternya untuk mencetak seluruhnya. Total seluruh hasil filter tetap tercantum di bawah.
        </p>
    @endif

    <table class="data-table" style="font-size:8.5pt;">
        <thead>
            <tr>
                <th style="width:22px;">No</th>
                <th style="width:62px;">Tgl Bayar</th>
                <th style="width:90px;">No. Pinjaman</th>
                <th style="width:70px;">No. Anggota</th>
                <th>Nama Anggota</th>
                <th style="text-align:right; width:82px;">Pokok</th>
                <th style="text-align:right; width:76px;">Jasa</th>
                <th style="text-align:right; width:66px;">Denda</th>
                <th style="text-align:right; width:86px;">Total</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($repayments as $i => $repayment)
                @php $batal = $repayment->isCancelled(); @endphp
                <tr @if ($batal) style="color:#869389;" @endif>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $repayment->paidOn()->format('d/m/Y') }}</td>
                    <td>{{ $repayment->loan?->loan_number }}</td>
                    <td>{{ $repayment->loan?->member?->member_number }}</td>
                    <td>{{ $repayment->loan?->member?->name }}</td>
                    <td style="text-align:right;">{{ $rp($repayment->principal_portion) }}</td>
                    <td style="text-align:right;">{{ $rp($repayment->interest_portion) }}</td>
                    <td style="text-align:right;">{{ $rp($repayment->penalty_portion) }}</td>
                    <td style="text-align:right;">{{ $rp($repayment->amount) }}</td>
                    <td>
                        @if ($batal)
                            <strong style="color:#A4432B;">BATAL</strong>{{ $repayment->cancellation_reason ? ' — '.$repayment->cancellation_reason : '' }}
                        @else
                            {{ $repayment->description }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10">Tidak ada angsuran pada periode dan filter ini.</td></tr>
            @endforelse
            @if ($dipotong)
                <tr>
                    <td colspan="5" style="text-align:right; font-weight:700;">TOTAL YANG TERCETAK</td>
                    <td style="text-align:right; font-weight:700;">{{ $rp($tercetakAktif->sum(fn ($r) => (float) $r->principal_portion)) }}</td>
                    <td style="text-align:right; font-weight:700;">{{ $rp($tercetakAktif->sum(fn ($r) => (float) $r->interest_portion)) }}</td>
                    <td style="text-align:right; font-weight:700;">{{ $rp($tercetakAktif->sum(fn ($r) => (float) $r->penalty_portion)) }}</td>
                    <td style="text-align:right; font-weight:700;">{{ $rp($tercetakAktif->sum(fn ($r) => (float) $r->amount)) }}</td>
                    <td></td>
                </tr>
            @endif
            <tr>
                <td colspan="5" style="text-align:right; font-weight:700;">TOTAL{{ $dipotong ? ' SELURUH HASIL FILTER' : '' }}</td>
                <td style="text-align:right; font-weight:700;">{{ $rp($totals['pokok']) }}</td>
                <td style="text-align:right; font-weight:700;">{{ $rp($totals['jasa']) }}</td>
                <td style="text-align:right; font-weight:700;">{{ $rp($totals['denda']) }}</td>
                <td style="text-align:right; font-weight:700;">{{ $rp($totals['total']) }}</td>
                <td style="font-size:7.5pt; color:#5C6E64;">angsuran batal tidak dijumlah</td>
            </tr>
        </tbody>
    </table>

    {{-- Angsuran = penerimaan kas, jadi memakai penandatangan Bukti Kas Masuk. --}}
    @include('prints.partials.signature-block', ['documentGroup' => 'kas_masuk'])
@endsection
