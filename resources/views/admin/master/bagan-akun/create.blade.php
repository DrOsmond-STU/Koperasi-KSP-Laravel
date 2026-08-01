@extends('layouts.app')

@section('title', 'Tambah Akun')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 640px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select, .field textarea { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; font-family: inherit; }
        .field-row { display: flex; gap: 14px; }
        .field-row .field { flex: 1; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .checkbox-field label { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--ink, inherit); font-weight: 600; }
        .checkbox-field input { width: auto; }
    </style>

    <h2>Tambah Akun</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.chart-of-accounts.store') }}">
            @csrf

            <div class="field-row">
                <div class="field">
                    <label>Kode Akun</label>
                    <input type="text" name="code" value="{{ old('code') }}" maxlength="10" required>
                </div>
                <div class="field">
                    <label>Nama Akun</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Tipe</label>
                    <select name="type" required>
                        <option value="">— Pilih —</option>
                        @foreach (['ASET', 'LIABILITAS', 'EKUITAS', 'PENDAPATAN', 'BEBAN'] as $type)
                            <option value="{{ $type }}" @selected(old('type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Saldo Normal</label>
                    <select name="normal_balance" required>
                        <option value="">— Pilih —</option>
                        <option value="DEBIT" @selected(old('normal_balance') === 'DEBIT')>DEBIT</option>
                        <option value="KREDIT" @selected(old('normal_balance') === 'KREDIT')>KREDIT</option>
                    </select>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Grup (opsional)</label>
                    <input type="text" name="group" value="{{ old('group') }}">
                </div>
                <div class="field">
                    <label>Laporan</label>
                    <select name="statement" required>
                        <option value="">— Pilih —</option>
                        <option value="NERACA" @selected(old('statement') === 'NERACA')>Neraca</option>
                        <option value="LABA_RUGI" @selected(old('statement') === 'LABA_RUGI')>Laba/Rugi</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Akun Induk (opsional)</label>
                <select name="parent_code" class="js-searchable">
                    <option value="">— Tidak ada (akun utama) —</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->code }}" @selected(old('parent_code') === $parent->code)>{{ $parent->code }} — {{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field checkbox-field">
                <label><input type="checkbox" name="is_postable" value="1" @checked(old('is_postable'))> Bisa dipakai langsung untuk transaksi (postable)</label>
                <p class="hint">Kosongkan jika akun ini hanya header/pengelompok, bukan tujuan posting jurnal.</p>
            </div>

            <div class="field">
                <label>Catatan (opsional)</label>
                <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
            </div>

            <button type="submit" class="btn-primary">Simpan Akun</button>
        </form>
    </div>
@endsection
