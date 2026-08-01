@extends('layouts.app')

@section('title', 'Tambah Kategori Aktiva Tetap')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
    </style>

    <h2>Tambah Kategori Aktiva Tetap</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.fixed-asset-categories.store') }}">
            @csrf

            <div class="field">
                <label>Kode Kategori</label>
                <input type="text" name="code" value="{{ old('code') }}" required>
            </div>
            <div class="field">
                <label>Nama Kategori</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label>Metode Penyusutan Default</label>
                <select name="default_depreciation_method" required>
                    <option value="garis_lurus">Garis Lurus</option>
                    <option value="saldo_menurun">Saldo Menurun</option>
                </select>
            </div>
            <div class="field">
                <label>Umur Ekonomis Default (bulan)</label>
                <input type="number" name="default_useful_life_months" value="{{ old('default_useful_life_months', 36) }}" required>
            </div>
            <div class="field">
                <label>Akun COA — Aset</label>
                <select name="coa_asset_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Akumulasi Penyusutan</label>
                <select name="coa_accumulated_depreciation_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Beban Penyusutan</label>
                <select name="coa_depreciation_expense_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">Simpan Kategori</button>
        </form>
    </div>
@endsection
