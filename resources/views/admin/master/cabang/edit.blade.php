@extends('layouts.app')

@section('title', 'Ubah Cabang')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select, .field textarea { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; font-family: inherit; }
        .field-row { display: flex; gap: 14px; }
        .field-row .field { flex: 1; }
        .field-check label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: var(--pine-ink); }
        .field-check input { width: auto; }
        .field-hint { font-size: 11px; color: var(--muted); margin-top: 5px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-link { color: var(--muted); text-decoration: none; font-size: 13px; margin-left: 12px; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
    </style>

    <h2>Ubah Cabang</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.branches.update', $branch) }}">
            @csrf
            @method('PUT')

            <div class="field-row">
                <div class="field">
                    <label for="code">Kode Cabang</label>
                    <input type="text" id="code" name="code" value="{{ old('code', $branch->code) }}" maxlength="30" required>
                </div>
                <div class="field">
                    <label for="name">Nama Cabang</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $branch->name) }}" maxlength="150" required>
                </div>
            </div>

            <div class="field">
                <label for="address">Alamat</label>
                <input type="text" id="address" name="address" value="{{ old('address', $branch->address) }}" maxlength="255">
            </div>

            <div class="field">
                <label for="parent_branch_id">Cabang Induk</label>
                <select id="parent_branch_id" name="parent_branch_id">
                    <option value="">— Tidak ada (kantor pusat) —</option>
                    @foreach ($parentOptions as $option)
                        <option value="{{ $option->id }}" @selected(old('parent_branch_id', $branch->parent_branch_id) == $option->id)>{{ $option->code }} — {{ $option->name }}</option>
                    @endforeach
                </select>
                <div class="field-hint">Cabang ini sendiri dan cabang di bawahnya sengaja tidak muncul di daftar, supaya rantai induk tidak melingkar.</div>
            </div>

            <div class="field">
                <label for="operational_date">Tanggal Operasional</label>
                <input type="date" id="operational_date" name="operational_date" value="{{ old('operational_date', $branch->operational_date?->toDateString()) }}">
            </div>

            <div class="field field-check">
                <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active))> Cabang aktif</label>
            </div>
            <div class="field-hint" style="margin-top: -8px; margin-bottom: 14px;">
                Menonaktifkan cabang tidak menghapus data apa pun — riwayat transaksinya tetap utuh dan tetap ikut dalam laporan.
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
            <a href="{{ route('admin.master.branches.index') }}" class="btn-link">Batal</a>
        </form>
    </div>
@endsection
