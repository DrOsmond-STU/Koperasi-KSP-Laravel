@extends('prints.layout')

@section('title', 'Bukti Retur Pembelian')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">BUKTI RETUR PEMBELIAN</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">No. Retur #{{ $return->id }}</p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:160px; padding:3px 0;">No. Pembelian Asal</td><td style="padding:3px 0;">: {{ $return->purchaseItem->purchaseTransaction->purchase_number }}</td></tr>
        <tr><td style="padding:3px 0;">Supplier</td><td style="padding:3px 0;">: {{ $return->purchaseItem->purchaseTransaction->supplier->name }}</td></tr>
        <tr><td style="padding:3px 0;">Barang</td><td style="padding:3px 0;">: {{ $return->purchaseItem->product->name }}</td></tr>
        <tr><td style="padding:3px 0;">Qty Retur</td><td style="padding:3px 0;">: {{ rtrim(rtrim(number_format($return->qty, 4, ',', '.'), '0'), ',') }}</td></tr>
        <tr><td style="padding:3px 0;">Nilai Retur</td><td style="padding:3px 0;">: Rp {{ number_format($return->amount, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Alasan</td><td style="padding:3px 0;">: {{ $return->stockReason->name }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal</td><td style="padding:3px 0;">: {{ $return->return_date->translatedFormat('d M Y') }}</td></tr>
        <tr><td style="padding:3px 0;">Dibuat oleh</td><td style="padding:3px 0;">: {{ $return->createdBy->name }}</td></tr>
    </table>

    @include('prints.partials.signature-block', [
        'documentGroup' => 'dokumen_gudang',
        'extraSigners' => [['label' => 'Dibuat oleh', 'name' => $return->createdBy->name]],
    ])
@endsection
