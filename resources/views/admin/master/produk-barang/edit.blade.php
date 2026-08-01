@extends('layouts.app')

@section('title', 'Ubah Barang')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
    </style>

    <h2>Ubah Barang — {{ $product->name }}</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.products.update', $product) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="field">
                <label>Foto Barang</label>
                @if ($product->image_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($product->image_path) }}" alt="Foto {{ $product->name }}" style="display:block; width:80px; height:80px; object-fit:cover; border-radius:8px; margin-bottom:8px; border:1px solid var(--line);">
                @endif
                <input type="file" name="image" accept="image/png,image/jpeg">
            </div>
            <div class="field">
                <label>Kode Barang</label>
                <input type="text" name="code" value="{{ old('code', $product->code) }}" required>
            </div>
            <div class="field">
                <label>Nama Barang</label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required>
            </div>
            <div class="field">
                <label>Kategori</label>
                <input type="text" name="category" value="{{ old('category', $product->category) }}">
            </div>
            <div class="field">
                <label>Satuan</label>
                <input type="text" name="unit" value="{{ old('unit', $product->unit) }}" placeholder="pcs, kg, dus, ..." required>
            </div>
            <div class="field">
                <label>Harga Beli</label>
                <input type="number" step="0.01" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price) }}">
            </div>
            <div class="field">
                <label>Harga Jual</label>
                <input type="number" step="0.01" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}">
            </div>
            <div class="field">
                <label>Akun COA — Persediaan</label>
                <select name="coa_inventory_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('coa_inventory_account_id', $product->coa_inventory_account_id) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — HPP</label>
                <select name="coa_cogs_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('coa_cogs_account_id', $product->coa_cogs_account_id) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Pendapatan Penjualan</label>
                <select name="coa_sales_revenue_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('coa_sales_revenue_account_id', $product->coa_sales_revenue_account_id) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection
