@extends('layouts.app')

@section('title', 'Ubah Akun')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 640px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select, .field textarea { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; font-family: inherit; }
        .field input[readonly] { background: var(--paper); color: var(--muted); cursor: not-allowed; }
        .field-row { display: flex; gap: 14px; }
        .field-row .field { flex: 1; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .warn-banner { background: #FBE7E1; color: var(--brick); border-radius: 9px; padding: 10px 14px; font-size: 12px; margin-bottom: 16px; }
        .checkbox-field label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; }
        .checkbox-field input { width: auto; }
    </style>

    <h2>Ubah Akun — {{ $account->code }} {{ $account->name }}</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($account->isProtected())
        <div class="warn-banner">
            Akun ini dipakai inti sistem (posting kas/penyesuaian/pelepasan otomatis) — kode akun dikunci dan tidak bisa dihapus. Field lain masih bisa diubah.
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.chart-of-accounts.update', $account) }}">
            @csrf
            @method('PUT')

            <div class="field-row">
                <div class="field">
                    <label>Kode Akun</label>
                    <input type="text" name="code" value="{{ old('code', $account->code) }}" maxlength="10" required @if ($account->isProtected()) readonly @endif>
                </div>
                <div class="field">
                    <label>Nama Akun</label>
                    <input type="text" name="name" value="{{ old('name', $account->name) }}" required>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Tipe</label>
                    <select name="type" required>
                        @foreach (['ASET', 'LIABILITAS', 'EKUITAS', 'PENDAPATAN', 'BEBAN'] as $type)
                            <option value="{{ $type }}" @selected(old('type', $account->type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Saldo Normal</label>
                    <select name="normal_balance" required>
                        <option value="DEBIT" @selected(old('normal_balance', $account->normal_balance) === 'DEBIT')>DEBIT</option>
                        <option value="KREDIT" @selected(old('normal_balance', $account->normal_balance) === 'KREDIT')>KREDIT</option>
                    </select>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Grup (opsional)</label>
                    <input type="text" name="group" value="{{ old('group', $account->group) }}">
                </div>
                <div class="field">
                    <label>Laporan</label>
                    <select name="statement" required>
                        <option value="NERACA" @selected(old('statement', $account->statement) === 'NERACA')>Neraca</option>
                        <option value="LABA_RUGI" @selected(old('statement', $account->statement) === 'LABA_RUGI')>Laba/Rugi</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Akun Induk (opsional)</label>
                <select name="parent_code" class="js-searchable">
                    <option value="">— Tidak ada (akun utama) —</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->code }}" @selected(old('parent_code', $account->parent_code) === $parent->code)>{{ $parent->code }} — {{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field checkbox-field">
                <label><input type="checkbox" name="is_postable" value="1" @checked(old('is_postable', $account->is_postable))> Bisa dipakai langsung untuk transaksi (postable)</label>
                <p class="hint">Kosongkan jika akun ini hanya header/pengelompok, bukan tujuan posting jurnal.</p>
            </div>

            <div class="field">
                <label>Catatan (opsional)</label>
                <textarea name="notes" rows="2">{{ old('notes', $account->notes) }}</textarea>
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection
