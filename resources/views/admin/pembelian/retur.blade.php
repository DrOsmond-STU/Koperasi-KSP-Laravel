@extends('layouts.app')

@section('title', 'Retur Pembelian')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: #A8472F; font-size: 12px; margin-top: 4px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Retur Pembelian</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-text" style="margin-bottom:14px;">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.pembelian.retur.store') }}">
            @csrf

            <div class="field">
                <label>Baris Pembelian Asal</label>
                <select name="purchase_item_id" required>
                    <option value="">— Pilih Baris Pembelian —</option>
                    @foreach ($purchaseItems as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->purchaseTransaction->purchase_number }} — {{ $item->product->name }} (qty dibeli: {{ rtrim(rtrim((string) $item->qty, '0'), '.') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Qty Retur</label>
                <input type="number" step="0.0001" name="qty" required>
            </div>
            <div class="field">
                <label>Alasan</label>
                <select name="stock_reason_id" required>
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">Simpan Retur</button>
        </form>
    </div>
@endsection
