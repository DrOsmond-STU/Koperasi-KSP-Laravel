@extends('prints.layout')

@section('title', $transaction->type === 'tarik' ? 'Bukti Kas Keluar' : 'Bukti Kas Masuk')

@section('print-content')
    @php
        // Transaksi yang dibatalkan (SavingsService::reverseTransaction()) tetap
        // bisa dicetak ulang — ditandai BATAL supaya tidak dipakai sebagai
        // bukti kas yang sah.
        $isCancelled = $transaction->isCancelled();
        $cancelledByName = $isCancelled && $transaction->cancelled_by
            ? \App\Models\User::query()->find($transaction->cancelled_by)?->name
            : null;
    @endphp

    @if ($isCancelled)
        <div style="position:fixed; top:300px; left:0; right:0; text-align:center; font-size:110pt; font-weight:700; color:#C0392B; opacity:0.15; transform:rotate(-30deg);">BATAL</div>
    @endif

    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">
        {{ $transaction->type === 'tarik' ? 'BUKTI KAS KELUAR' : 'BUKTI KAS MASUK' }}
    </h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 {{ $isCancelled ? '10px' : '16px' }}; text-align:center;">No. Transaksi #{{ $transaction->id }}</p>

    @if ($isCancelled)
        <div style="border:2px solid #C0392B; color:#C0392B; background:#FDEDEC; padding:8px 10px; margin:0 0 14px; text-align:center;">
            <div style="font-size:16pt; font-weight:700; letter-spacing:4px;">BATAL</div>
            <div style="font-size:9pt; margin-top:2px;">
                Transaksi ini telah dibatalkan pada {{ $transaction->cancelled_at->translatedFormat('d M Y H:i') }}@if ($cancelledByName) oleh {{ $cancelledByName }}@endif
                @if ($transaction->cancellation_reason) — Alasan: {{ $transaction->cancellation_reason }}@endif
            </div>
        </div>
    @endif

    <table style="margin-bottom:16px;">
        <tr><td style="width:160px; padding:3px 0;">Nama Anggota</td><td style="padding:3px 0;">: {{ $transaction->savingsAccount->member->name }}</td></tr>
        <tr><td style="padding:3px 0;">No. Rekening</td><td style="padding:3px 0;">: {{ $transaction->savingsAccount->account_number }}</td></tr>
        <tr><td style="padding:3px 0;">Jenis Transaksi</td><td style="padding:3px 0;">: {{ $transaction->type === 'tarik' ? 'Penarikan Simpanan' : 'Setoran Simpanan' }}</td></tr>
        <tr><td style="padding:3px 0;">Status</td><td style="padding:3px 0;">: @if ($isCancelled)<strong style="color:#C0392B;">BATAL</strong>@else<strong>AKTIF</strong>@endif</td></tr>
        <tr><td style="padding:3px 0;">Nominal</td><td style="padding:3px 0;">: Rp {{ number_format($transaction->amount, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Saldo Setelah Transaksi</td><td style="padding:3px 0;">: Rp {{ number_format($transaction->balance_after, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal Transaksi</td><td style="padding:3px 0;">: {{ $transaction->transactionOn()->translatedFormat('d M Y') }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal Input</td><td style="padding:3px 0;">: {{ $transaction->created_at->translatedFormat('d M Y H:i') }}</td></tr>
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
