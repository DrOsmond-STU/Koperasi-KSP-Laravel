@extends('layouts.app')

@section('title', 'Koreksi Persediaan Baru')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
    </style>

    <h2>Koreksi Persediaan Baru</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.persediaan.koreksi.store') }}">
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
                <label>Barang</label>
                <select name="product_id" required class="js-searchable">
                    <option value="">— Pilih Barang —</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Stok Fisik Hasil Opname</label>
                <input type="number" step="0.0001" name="physical_qty" required>
            </div>
            <div class="field">
                <label>Alasan</label>
                <select name="stock_reason_id" required>
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">Simpan Koreksi</button>
        </form>
    </div>
@endsection
