@extends('prints.layout')

@section('title', 'Pengajuan Penarikan Simpanan')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">SURAT PENGAJUAN PENARIKAN SIMPANAN</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">No. Pengajuan #{{ $withdrawalRequest->id }}</p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:160px; padding:3px 0;">Nama Anggota</td><td style="padding:3px 0;">: {{ $withdrawalRequest->member->name }}</td></tr>
        <tr><td style="padding:3px 0;">No. Anggota</td><td style="padding:3px 0;">: {{ $withdrawalRequest->member->member_number }}</td></tr>
        <tr><td style="padding:3px 0;">No. Rekening</td><td style="padding:3px 0;">: {{ $withdrawalRequest->savingsAccount->account_number }}</td></tr>
        <tr><td style="padding:3px 0;">Nominal Penarikan</td><td style="padding:3px 0;">: Rp {{ number_format($withdrawalRequest->amount, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Diajukan oleh</td><td style="padding:3px 0;">: {{ $withdrawalRequest->requestedBy->name }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal Pengajuan</td><td style="padding:3px 0;">: {{ $withdrawalRequest->created_at->translatedFormat('d M Y H:i') }}</td></tr>
        <tr><td style="padding:3px 0;">Status</td><td style="padding:3px 0;">: {{ ucfirst($withdrawalRequest->status) }}</td></tr>
        @if ($withdrawalRequest->reviewedBy)
            <tr><td style="padding:3px 0;">Diputuskan oleh</td><td style="padding:3px 0;">: {{ $withdrawalRequest->reviewedBy->name }} — {{ optional($withdrawalRequest->reviewed_at)->translatedFormat('d M Y H:i') }}</td></tr>
        @endif
        @if ($withdrawalRequest->decision_notes)
            <tr><td style="padding:3px 0;">Catatan</td><td style="padding:3px 0;">: {{ $withdrawalRequest->decision_notes }}</td></tr>
        @endif
    </table>

    @include('prints.partials.signature-block', [
        'documentGroup' => 'pengajuan_penarikan',
        'extraSigners' => [['label' => 'Pemohon', 'name' => $withdrawalRequest->member->name]],
    ])
@endsection
