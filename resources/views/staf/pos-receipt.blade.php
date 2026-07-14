@extends('layouts.app')

@section('title', 'Struk Penjualan')

@section('content')
    <style>
        .receipt { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 420px; font-size: 13px; }
        .receipt h3 { margin: 0 0 4px; }
        .receipt .muted { color: var(--muted); font-size: 12px; margin-bottom: 14px; }
        .receipt table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .receipt td { padding: 4px 0; }
        .receipt .total-row { border-top: 1px dashed var(--line); font-weight: 700; }
        .btn-print { padding: 8px 14px; background: var(--pine); color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
        @media print { .no-print { display: none; } }
    </style>

    <div class="receipt">
        <h3>Struk Penjualan</h3>
        <p class="muted">{{ $sale->sale_number }} — {{ $sale->sale_date->translatedFormat('d M Y') }}</p>

        <table>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product->name }} × {{ rtrim(rtrim((string) $item->qty, '0'), '.') }}</td>
                    <td style="text-align:right;">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total</td>
                <td style="text-align:right;">Rp {{ number_format((float) $sale->total_amount, 0, ',', '.') }}</td>
            </tr>
        </table>

        <p class="muted">
            Metode Bayar: {{ $sale->payment_method === 'tunai' ? 'Tunai' : 'Potong Saldo Simpanan — '.$sale->savingsAccount?->account_number.' ('.$sale->savingsAccount?->member?->name.')' }}
        </p>

        <button class="btn-print no-print" onclick="window.print()">Cetak Struk</button>
    </div>
@endsection
