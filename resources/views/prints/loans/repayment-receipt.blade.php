@extends('prints.layout')

@section('title', 'Bukti Kas Masuk — Angsuran')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">BUKTI KAS MASUK — PEMBAYARAN ANGSURAN</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">No. Transaksi #{{ $repayment->id }}</p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:160px; padding:3px 0;">Nama Anggota</td><td style="padding:3px 0;">: {{ $repayment->loan->member->name }}</td></tr>
        <tr><td style="padding:3px 0;">No. Pinjaman</td><td style="padding:3px 0;">: {{ $repayment->loan->loan_number }}</td></tr>
        <tr><td style="padding:3px 0;">Nominal Bayar</td><td style="padding:3px 0;">: Rp {{ number_format($repayment->amount, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Porsi Pokok</td><td style="padding:3px 0;">: Rp {{ number_format($repayment->principal_portion, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Porsi Jasa</td><td style="padding:3px 0;">: Rp {{ number_format($repayment->interest_portion, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Sisa Tunggakan</td><td style="padding:3px 0;">: Rp {{ number_format($repayment->balance_after, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal</td><td style="padding:3px 0;">: {{ $repayment->created_at->translatedFormat('d M Y H:i') }}</td></tr>
        @if ($repayment->description)
            <tr><td style="padding:3px 0;">Keterangan</td><td style="padding:3px 0;">: {{ $repayment->description }}</td></tr>
        @endif
    </table>

    @include('prints.partials.signature-block', [
        'documentGroup' => 'kas_masuk',
        'extraSigners' => [
            ['label' => 'Penyerah', 'name' => $repayment->loan->member->name],
        ],
    ])
@endsection
