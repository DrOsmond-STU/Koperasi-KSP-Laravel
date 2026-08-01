@extends('prints.layout')

@section('title', $transaction->type === 'tarik' ? 'Bukti Kas Keluar' : 'Bukti Kas Masuk')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">
        {{ $transaction->type === 'tarik' ? 'BUKTI KAS KELUAR' : 'BUKTI KAS MASUK' }}
    </h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">No. Transaksi #{{ $transaction->id }}</p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:160px; padding:3px 0;">Nama Anggota</td><td style="padding:3px 0;">: {{ $transaction->savingsAccount->member->name }}</td></tr>
        <tr><td style="padding:3px 0;">No. Rekening</td><td style="padding:3px 0;">: {{ $transaction->savingsAccount->account_number }}</td></tr>
        <tr><td style="padding:3px 0;">Jenis Transaksi</td><td style="padding:3px 0;">: {{ $transaction->type === 'tarik' ? 'Penarikan Simpanan' : 'Setoran Simpanan' }}</td></tr>
        <tr><td style="padding:3px 0;">Nominal</td><td style="padding:3px 0;">: Rp {{ number_format($transaction->amount, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Saldo Setelah Transaksi</td><td style="padding:3px 0;">: Rp {{ number_format($transaction->balance_after, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal</td><td style="padding:3px 0;">: {{ $transaction->created_at->translatedFormat('d M Y H:i') }}</td></tr>
        <tr><td style="padding:3px 0;">Diproses oleh</td><td style="padding:3px 0;">: {{ $transaction->createdBy->name }}</td></tr>
        @if ($transaction->description)
            <tr><td style="padding:3px 0;">Keterangan</td><td style="padding:3px 0;">: {{ $transaction->description }}</td></tr>
        @endif
    </table>

    @include('prints.partials.signature-block', [
        'documentGroup' => $documentGroup,
        'extraSigners' => [
            ['label' => 'Teller', 'name' => $transaction->createdBy->name],
            ['label' => $transaction->type === 'tarik' ? 'Penerima' : 'Penyerah', 'name' => $transaction->savingsAccount->member->name],
        ],
    ])
@endsection
