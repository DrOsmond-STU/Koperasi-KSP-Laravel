@extends('prints.layout')

@section('title', 'Bukti Pembelian')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">BUKTI PEMBELIAN BARANG</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">No. {{ $purchase->purchase_number }}</p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:160px; padding:3px 0;">Supplier</td><td style="padding:3px 0;">: {{ $purchase->supplier->name }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal</td><td style="padding:3px 0;">: {{ $purchase->purchase_date->translatedFormat('d M Y') }}</td></tr>
        <tr><td style="padding:3px 0;">Metode Bayar</td><td style="padding:3px 0;">: {{ ucfirst($purchase->payment_method) }}</td></tr>
        <tr><td style="padding:3px 0;">Status</td><td style="padding:3px 0;">: {{ ucfirst(str_replace('_', ' ', $purchase->status)) }}</td></tr>
        <tr><td style="padding:3px 0;">Dibuat oleh</td><td style="padding:3px 0;">: {{ $purchase->createdBy->name }}</td></tr>
        @if ($purchase->approvedBy)
            <tr><td style="padding:3px 0;">Disetujui oleh</td><td style="padding:3px 0;">: {{ $purchase->approvedBy->name }}</td></tr>
        @endif
    </table>

    <table class="data-table" style="margin-bottom: 10px;">
        <thead><tr><th>Barang</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Harga Satuan</th><th style="text-align:right;">Subtotal</th></tr></thead>
        <tbody>
            @foreach ($purchase->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td style="text-align:right;">{{ rtrim(rtrim(number_format($item->qty, 4, ',', '.'), '0'), ',') }}</td>
                    <td style="text-align:right;">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td style="text-align:right;">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" style="text-align:right; font-weight:700;">Total</td>
                <td style="text-align:right; font-weight:700;">Rp {{ number_format($purchase->total_amount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @include('prints.partials.signature-block', [
        'documentGroup' => 'dokumen_gudang',
        'extraSigners' => [['label' => 'Dibuat oleh', 'name' => $purchase->createdBy->name]],
    ])
@endsection
