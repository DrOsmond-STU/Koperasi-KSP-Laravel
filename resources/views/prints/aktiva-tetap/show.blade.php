@extends('prints.layout')

@section('title', 'Bukti Pembelian Aktiva Tetap')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">BUKTI PEMBELIAN AKTIVA TETAP</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">No. {{ $asset->code }}</p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:180px; padding:3px 0;">Nama Aktiva</td><td style="padding:3px 0;">: {{ $asset->name }}</td></tr>
        <tr><td style="padding:3px 0;">Kategori</td><td style="padding:3px 0;">: {{ $asset->category->name }}</td></tr>
        <tr><td style="padding:3px 0;">Supplier</td><td style="padding:3px 0;">: {{ $asset->supplier?->name ?? '-' }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal Perolehan</td><td style="padding:3px 0;">: {{ $asset->acquisition_date->translatedFormat('d M Y') }}</td></tr>
        <tr><td style="padding:3px 0;">Nilai Perolehan</td><td style="padding:3px 0;">: Rp {{ number_format($asset->acquisition_cost, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Metode Bayar</td><td style="padding:3px 0;">: {{ ucfirst($asset->payment_method) }}</td></tr>
        <tr><td style="padding:3px 0;">Umur Manfaat</td><td style="padding:3px 0;">: {{ $asset->useful_life_months }} bulan</td></tr>
        <tr><td style="padding:3px 0;">Status</td><td style="padding:3px 0;">: {{ ucfirst($asset->status) }}</td></tr>
        <tr><td style="padding:3px 0;">Dibuat oleh</td><td style="padding:3px 0;">: {{ $asset->createdBy->name }}</td></tr>
        @if ($asset->approvedBy)
            <tr><td style="padding:3px 0;">Disetujui oleh</td><td style="padding:3px 0;">: {{ $asset->approvedBy->name }}</td></tr>
        @endif
    </table>

    @include('prints.partials.signature-block', [
        'documentGroup' => 'aktiva_tetap',
        'extraSigners' => [['label' => 'Dibuat oleh', 'name' => $asset->createdBy->name]],
    ])
@endsection
