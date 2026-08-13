@extends('layouts.app')

@section('title', 'Ubah Kategori Produk Simpanan')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field textarea { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; font-family: inherit; }
        .field input[readonly] { background: var(--paper); color: var(--muted); }
        .checkbox-row { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
        .checkbox-row input { width: auto; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 12px; color: var(--muted); margin-top: 6px; line-height: 1.5; }
    </style>

    <h2>Ubah Kategori Produk Simpanan</h2>
    <p style="margin-top: -8px;"><a href="{{ route('admin.master.savings-product-categories.index') }}" class="btn-link">&larr; Kembali ke daftar kategori</a></p>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.savings-product-categories.update', $category) }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label>Kode</label>
                <input type="text" value="{{ $category->code }}" readonly>
                <p class="hint">Kode tidak bisa diubah karena tersimpan di setiap produk simpanan yang memakainya.</p>
            </div>

            <div class="field">
                <label>Nama</label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required maxlength="150">
            </div>

            <div class="field">
                <label>Keterangan (opsional)</label>
                <textarea name="description" rows="2" maxlength="255">{{ old('description', $category->description) }}</textarea>
            </div>

            <div class="checkbox-row">
                <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $category->is_active))>
                <label for="is_active" style="margin: 0;">Aktif — kategori nonaktif tidak muncul saat menambah produk simpanan</label>
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection
