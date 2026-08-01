@extends('layouts.app')

@section('title', 'Ubah Jenis Retribusi')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field-row { display: flex; gap: 14px; }
        .field-row .field { flex: 1; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
    </style>

    <h2>Ubah Jenis Retribusi — {{ $type->name }}</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.retribution-types.update', $type) }}">
            @csrf
            @method('PUT')

            <div class="field-row">
                <div class="field">
                    <label>Kode</label>
                    <input type="text" name="code" value="{{ old('code', $type->code) }}" required>
                </div>
                <div class="field">
                    <label>Nama Retribusi</label>
                    <input type="text" name="name" value="{{ old('name', $type->name) }}" required>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Persentase (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="percentage" value="{{ old('percentage', $type->percentage) }}" required>
                </div>
                <div class="field">
                    <label>Urutan</label>
                    <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $type->sort_order) }}">
                </div>
            </div>

            <div class="field">
                <label>Akun COA — Pendapatan</label>
                <select name="coa_revenue_account_id" class="js-searchable">
                    <option value="">— Belum di-link —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('coa_revenue_account_id', $type->coa_revenue_account_id) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active)) style="width:auto;"> Aktif</label>
                <p class="hint">Tidak bisa diaktifkan sebelum akun COA di-link.</p>
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection
