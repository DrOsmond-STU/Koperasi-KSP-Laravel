@extends('layouts.app')

@section('title', 'Ubah Jenis Anggota')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field-row { display: flex; gap: 14px; }
        .field-row .field { flex: 1; }
        .field-check label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: var(--pine-ink); }
        .field-check input { width: auto; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
    </style>

    <h2>Ubah Jenis Anggota — {{ $type->name }}</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.member-types.update', $type) }}">
            @csrf
            @method('PUT')

            <div class="field-row">
                <div class="field">
                    <label>Kode</label>
                    <input type="text" name="code" value="{{ old('code', $type->code) }}" required>
                </div>
                <div class="field">
                    <label>Nama Jenis Anggota</label>
                    <input type="text" name="name" value="{{ old('name', $type->name) }}" required>
                </div>
            </div>

            <div class="field">
                <label>Nominal Simpanan Wajib Default (Rp)</label>
                <input type="number" step="0.01" min="0" name="mandatory_savings_default_amount" value="{{ old('mandatory_savings_default_amount', $type->mandatory_savings_default_amount) }}">
            </div>

            <div class="field field-check">
                <label><input type="checkbox" name="has_voting_rights" value="1" @checked(old('has_voting_rights', $type->has_voting_rights))> Punya hak suara (RAT)</label>
            </div>
            <div class="field field-check">
                <label><input type="checkbox" name="requires_mandatory_savings" value="1" @checked(old('requires_mandatory_savings', $type->requires_mandatory_savings))> Wajib simpanan wajib</label>
            </div>
            <div class="field field-check">
                <label><input type="checkbox" name="counts_toward_shu" value="1" @checked(old('counts_toward_shu', $type->counts_toward_shu))> Dihitung dalam pembagian SHU</label>
            </div>
            <div class="field field-check">
                <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active))> Aktif</label>
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection
