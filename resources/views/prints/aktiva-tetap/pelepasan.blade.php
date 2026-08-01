@extends('prints.layout')

@section('title', 'Bukti Pelepasan Aktiva Tetap')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">BUKTI PELEPASAN AKTIVA TETAP</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">No. Pelepasan #{{ $disposal->id }}</p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:180px; padding:3px 0;">Nama Aktiva</td><td style="padding:3px 0;">: {{ $disposal->fixedAsset->name }} ({{ $disposal->fixedAsset->code }})</td></tr>
        <tr><td style="padding:3px 0;">Jenis Pelepasan</td><td style="padding:3px 0;">: {{ ucfirst($disposal->disposal_type) }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal</td><td style="padding:3px 0;">: {{ $disposal->disposal_date->translatedFormat('d M Y') }}</td></tr>
        <tr><td style="padding:3px 0;">Nilai Buku Saat Pelepasan</td><td style="padding:3px 0;">: Rp {{ number_format($disposal->book_value_at_disposal, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Nilai Jual</td><td style="padding:3px 0;">: Rp {{ number_format($disposal->sale_amount, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">{{ $disposal->isGain() ? 'Laba' : 'Rugi' }} Pelepasan</td><td style="padding:3px 0;">: Rp {{ number_format(abs($disposal->gain_loss_amount), 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Dibuat oleh</td><td style="padding:3px 0;">: {{ $disposal->createdBy->name }}</td></tr>
    </table>

    @include('prints.partials.signature-block', [
        'documentGroup' => 'aktiva_tetap',
        'extraSigners' => [['label' => 'Dibuat oleh', 'name' => $disposal->createdBy->name]],
    ])
@endsection
