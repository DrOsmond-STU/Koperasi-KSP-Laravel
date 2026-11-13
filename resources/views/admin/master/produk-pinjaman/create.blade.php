@extends('layouts.app')

@section('title', 'Tambah Produk Pinjaman')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 600px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    </style>

    <h2>Tambah Produk Pinjaman</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.loan-products.store') }}">
            @csrf

            <div class="field"><label>Kode Produk</label><input type="text" name="code" value="{{ old('code') }}" required></div>
            <div class="field"><label>Nama Produk</label><input type="text" name="name" value="{{ old('name') }}" required></div>

            <div class="grid-2">
                <div class="field"><label>Plafon Minimum (Rp)</label><input type="number" step="0.01" name="min_plafon" value="{{ old('min_plafon', 500000) }}" required></div>
                <div class="field"><label>Plafon Maksimum (Rp)</label><input type="number" step="0.01" name="max_plafon" value="{{ old('max_plafon', 50000000) }}" required></div>
            </div>
            <div class="field">
                <label>Satuan Tenor</label>
                <select name="tenor_unit" required>
                    <option value="hari" @selected(old('tenor_unit', 'hari') === 'hari')>Hari (ditagih harian — mis. Pinjaman Anggota)</option>
                    <option value="bulan" @selected(old('tenor_unit') === 'bulan')>Bulan (potong gaji bulanan — mis. Piutang Karyawan)</option>
                </select>
            </div>
            <div class="grid-2">
                <div class="field"><label>Tenor Minimum</label><input type="number" name="min_tenor_days" value="{{ old('min_tenor_days', 100) }}" required></div>
                <div class="field"><label>Tenor Maksimum</label><input type="number" name="max_tenor_days" value="{{ old('max_tenor_days', 200) }}" required></div>
            </div>

            <div class="field">
                <label>Metode Perhitungan</label>
                <select name="calculation_method" required>
                    <option value="flat">Flat</option>
                    <option value="efektif">Efektif (Menurun)</option>
                    <option value="anuitas">Anuitas</option>
                </select>
            </div>
            <div class="field">
                <label>Tarif Jasa (%)</label>
                <input type="number" step="0.001" name="initial_rate_percentage" value="{{ old('initial_rate_percentage', 10) }}" required>
                <p class="hint">Untuk satuan HARI: tarif FLAT untuk seluruh tenor (mis. 10% berarti jasa totalnya 10% dari pokok, dibagi rata per hari selama tenor). Untuk satuan BULAN: tarif per TAHUN seperti biasa (dibagi 12 untuk cicilan bulanan).</p>
            </div>
            <div class="field"><label>Biaya Provisi (%)</label><input type="number" step="0.01" name="provision_fee_percentage" value="{{ old('provision_fee_percentage', 1) }}"></div>
            <div class="field"><label>Denda Keterlambatan (%/hari)</label><input type="number" step="0.001" name="penalty_percentage_per_day" value="{{ old('penalty_percentage_per_day', 0.1) }}"></div>
            <div class="field">
                <label>Ambang Plafon untuk Approval Berjenjang (Rp)</label>
                <input type="number" step="0.01" name="approval_threshold" value="{{ old('approval_threshold') }}">
                <p class="hint">Pengajuan di atas nominal ini butuh 2 approval berbeda orang; kosongkan bila cukup 1 approval untuk semua nominal.</p>
            </div>

            <div class="field">
                <label>Akun COA — Piutang Pinjaman</label>
                <select name="coa_receivable_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Pendapatan Jasa</label>
                <select name="coa_interest_income_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Pendapatan Provisi</label>
                <select name="coa_provision_income_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Piutang Denda</label>
                <select name="coa_penalty_receivable_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">Simpan Produk</button>
        </form>
    </div>
@endsection
