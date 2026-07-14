@extends('layouts.app')

@section('title', 'Tambah Produk Simpanan')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: #A8472F; font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
    </style>

    <h2>Tambah Produk Simpanan</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.savings-products.store') }}">
            @csrf

            <div class="field">
                <label>Kode Produk</label>
                <input type="text" name="code" value="{{ old('code') }}" required>
            </div>
            <div class="field">
                <label>Nama Produk</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label>Kategori</label>
                <select name="category" required>
                    <option value="pokok">Simpanan Pokok</option>
                    <option value="wajib">Simpanan Wajib</option>
                    <option value="sukarela" selected>Simpanan Sukarela</option>
                    <option value="berjangka">Simpanan Berjangka</option>
                </select>
            </div>
            <div class="field">
                <label>Metode Perhitungan Bunga</label>
                <select name="interest_method" required>
                    <option value="flat">Flat</option>
                    <option value="saldo_harian">Saldo Harian</option>
                    <option value="saldo_rata_rata_bulanan">Saldo Rata-rata Bulanan</option>
                    <option value="tiered">Tiered (bertingkat)</option>
                </select>
            </div>
            <div class="field">
                <label>Tarif Bunga/Jasa Awal (%)</label>
                <input type="number" step="0.001" name="initial_rate_percentage" value="{{ old('initial_rate_percentage', 0) }}" required>
                <p class="hint">Berlaku sejak hari ini — perubahan berikutnya tidak mengubah transaksi yang sudah lewat.</p>
            </div>
            <div class="field">
                <label>Setoran Awal Minimum</label>
                <input type="number" step="0.01" name="minimum_initial_deposit" value="{{ old('minimum_initial_deposit', 0) }}">
            </div>
            <div class="field">
                <label>Setoran Berikutnya Minimum</label>
                <input type="number" step="0.01" name="minimum_subsequent_deposit" value="{{ old('minimum_subsequent_deposit', 0) }}">
            </div>
            <div class="field">
                <label>Akun COA — Kewajiban Simpanan</label>
                <select name="coa_liability_account_id" required>
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Beban Bunga Simpanan</label>
                <select name="coa_interest_expense_account_id" required>
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">Simpan Produk</button>
        </form>
    </div>
@endsection
