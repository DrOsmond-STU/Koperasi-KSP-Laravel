@extends('layouts.app')

@section('title', 'Akun Jurnal Produk Pinjaman')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 600px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field small { display: block; font-size: 11px; color: var(--muted); margin-top: 4px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
    </style>

    <h2>Akun Jurnal — {{ $product->name }}</h2>
    <p class="error-text" style="color: var(--muted);">
        Akun-akun di bawah dipakai saat pinjaman produk ini dicairkan dan diangsur.
        Hanya akun yang bisa diposting (bukan akun header) yang bisa dipilih.
    </p>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.loan-products.accounts.update', $product) }}">
            @csrf
            @method('PUT')

            @php
                $accountFields = [
                    'coa_receivable_account_id' => ['Piutang Pinjaman', 'Didebit sebesar pokok saat pencairan.'],
                    'coa_interest_income_account_id' => ['Pendapatan Jasa', 'Diakui saat angsuran diterima.'],
                    'coa_provision_income_account_id' => ['Pendapatan Provisi', 'Dikredit sebesar biaya provisi saat pencairan.'],
                    'coa_penalty_receivable_account_id' => ['Piutang Denda', 'Dipakai saat angsuran terlambat.'],
                ];
            @endphp

            @foreach ($accountFields as $field => [$label, $hint])
                <div class="field">
                    <label>Akun COA — {{ $label }}</label>
                    <select name="{{ $field }}" required class="js-searchable">
                        <option value="">— Pilih Akun —</option>
                        @foreach ($postableAccounts as $account)
                            <option value="{{ $account->id }}" @selected(old($field, $product->{$field}) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <small>{{ $hint }}</small>
                </div>
            @endforeach

            <div class="field">
                <label>Akun COA — Kas Pencairan</label>
                <select name="coa_cash_account_id" class="js-searchable">
                    <option value="">— Pakai akun kas bawaan (1101) —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('coa_cash_account_id', $product->coa_cash_account_id) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
                <small>Akun yang dikredit saat pinjaman dicairkan. Kosongkan hanya bila akun 1101 memang akun kas yang dipakai koperasi.</small>
            </div>

            <button type="submit" class="btn-primary">Simpan Akun Jurnal</button>
        </form>
    </div>
@endsection
