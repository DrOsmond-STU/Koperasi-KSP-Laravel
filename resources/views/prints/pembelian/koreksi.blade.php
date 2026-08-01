@extends('prints.layout')

@section('title', 'Bukti Koreksi Persediaan')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">BUKTI KOREKSI PERSEDIAAN</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">No. Koreksi #{{ $adjustment->id }}</p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:180px; padding:3px 0;">Barang</td><td style="padding:3px 0;">: {{ $adjustment->product->name }}</td></tr>
        <tr><td style="padding:3px 0;">Qty Sistem</td><td style="padding:3px 0;">: {{ rtrim(rtrim(number_format($adjustment->system_qty, 4, ',', '.'), '0'), ',') }}</td></tr>
        <tr><td style="padding:3px 0;">Qty Fisik</td><td style="padding:3px 0;">: {{ rtrim(rtrim(number_format($adjustment->physical_qty, 4, ',', '.'), '0'), ',') }}</td></tr>
        <tr><td style="padding:3px 0;">Selisih</td><td style="padding:3px 0;">: {{ $adjustment->isSurplus() ? '+' : '' }}{{ rtrim(rtrim(number_format($adjustment->variance_qty, 4, ',', '.'), '0'), ',') }} ({{ $adjustment->isSurplus() ? 'Lebih' : 'Kurang' }})</td></tr>
        <tr><td style="padding:3px 0;">Nilai</td><td style="padding:3px 0;">: Rp {{ number_format($adjustment->amount, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Alasan</td><td style="padding:3px 0;">: {{ $adjustment->stockReason->name }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal</td><td style="padding:3px 0;">: {{ $adjustment->adjustment_date->translatedFormat('d M Y') }}</td></tr>
        <tr><td style="padding:3px 0;">Status</td><td style="padding:3px 0;">: {{ ucfirst(str_replace('_', ' ', $adjustment->status)) }}</td></tr>
        <tr><td style="padding:3px 0;">Dibuat oleh</td><td style="padding:3px 0;">: {{ $adjustment->createdBy->name }}</td></tr>
        @if ($adjustment->approvedBy)
            <tr><td style="padding:3px 0;">Disetujui oleh</td><td style="padding:3px 0;">: {{ $adjustment->approvedBy->name }}</td></tr>
        @endif
    </table>

    @include('prints.partials.signature-block', [
        'documentGroup' => 'dokumen_gudang',
        'extraSigners' => [['label' => 'Dibuat oleh', 'name' => $adjustment->createdBy->name]],
    ])
@endsection
