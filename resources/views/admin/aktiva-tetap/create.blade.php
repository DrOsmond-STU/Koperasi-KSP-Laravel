@extends('layouts.app')

@section('title', 'Aktiva Tetap Baru')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 620px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select, .field textarea { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
    </style>

    <h2>Aktiva Tetap Baru</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.aktiva-tetap.store') }}">
            @csrf

            <div class="field">
                <label>Cabang / Lokasi</label>
                <select name="branch_id" required>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Kategori</label>
                <select name="fixed_asset_category_id" required>
                    <option value="">— Pilih Kategori —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Kode Aset</label>
                <input type="text" name="code" value="{{ old('code') }}" required>
            </div>
            <div class="field">
                <label>Nama Aset</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label>Deskripsi</label>
                <textarea name="description" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="field">
                <label>No. Dokumen Kepemilikan</label>
                <input type="text" name="ownership_document_number" value="{{ old('ownership_document_number') }}">
            </div>
            <div class="field">
                <label>Tanggal Perolehan</label>
                <input type="date" name="acquisition_date" value="{{ old('acquisition_date', now()->toDateString()) }}" required>
            </div>
            <div class="field">
                <label>Nilai Perolehan</label>
                <input type="number" step="0.01" name="acquisition_cost" required>
            </div>
            <div class="field">
                <label>Nilai Residu</label>
                <input type="number" step="0.01" name="residual_value" value="{{ old('residual_value', 0) }}" required>
            </div>
            <div class="field">
                <label>Umur Ekonomis (bulan)</label>
                <input type="number" name="useful_life_months" value="{{ old('useful_life_months', 36) }}" required>
            </div>
            <div class="field">
                <label>Metode Penyusutan</label>
                <select name="depreciation_method" required>
                    <option value="garis_lurus">Garis Lurus</option>
                    <option value="saldo_menurun">Saldo Menurun</option>
                </select>
            </div>
            <div class="field">
                <label>Tarif Penyusutan % (khusus Saldo Menurun)</label>
                <input type="number" step="0.001" name="depreciation_rate_percentage" value="{{ old('depreciation_rate_percentage') }}">
            </div>
            <div class="field">
                <label>Metode Bayar</label>
                <select name="payment_method" required>
                    <option value="tunai">Tunai</option>
                    <option value="kredit">Kredit</option>
                </select>
            </div>
            <div class="field">
                <label>Supplier (jika kredit)</label>
                <select name="supplier_id" class="js-searchable">
                    <option value="">— Tidak Berlaku —</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">Simpan Aktiva Tetap</button>
        </form>
    </div>
@endsection
