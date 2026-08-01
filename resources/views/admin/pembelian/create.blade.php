@extends('layouts.app')

@section('title', 'Pembelian Baru')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 760px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .item-row { display: grid; grid-template-columns: 3fr 1fr 1fr; gap: 10px; margin-bottom: 8px; }
        .item-row select, .item-row input { padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; }
        .hint { font-size: 11px; color: var(--muted); margin: 8px 0; }
    </style>

    <h2>Pembelian Baru</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.pembelian.store') }}">
            @csrf

            <div class="field">
                <label>Cabang</label>
                <select name="branch_id" required>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Supplier</label>
                <select name="supplier_id" required class="js-searchable">
                    <option value="">— Pilih Supplier —</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Metode Bayar</label>
                <select name="payment_method" required>
                    <option value="tunai">Tunai</option>
                    <option value="kredit">Kredit</option>
                </select>
            </div>

            <p class="hint">Isi baris barang yang dibeli. Baris yang dibiarkan kosong akan diabaikan.</p>

            @for ($i = 0; $i < 8; $i++)
                <div class="item-row">
                    <select name="items[{{ $i }}][product_id]" class="js-searchable">
                        <option value="">— Pilih Barang —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.0001" name="items[{{ $i }}][qty]" placeholder="Qty">
                    <input type="number" step="0.01" name="items[{{ $i }}][unit_price]" placeholder="Harga Beli">
                </div>
            @endfor

            <button type="submit" class="btn-primary" style="margin-top: 14px;">Simpan Pembelian</button>
        </form>
    </div>
@endsection
