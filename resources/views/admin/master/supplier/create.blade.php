@extends('layouts.app')

@section('title', 'Tambah Supplier')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: #A8472F; font-size: 12px; margin-top: 4px; }
    </style>

    <h2>Tambah Supplier</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.suppliers.store') }}">
            @csrf

            <div class="field">
                <label>Kode Supplier</label>
                <input type="text" name="code" value="{{ old('code') }}" required>
            </div>
            <div class="field">
                <label>Nama Supplier</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label>Jenis</label>
                <input type="text" name="type" value="{{ old('type') }}">
            </div>
            <div class="field">
                <label>Kontak</label>
                <input type="text" name="contact_name" value="{{ old('contact_name') }}">
            </div>
            <div class="field">
                <label>Telepon</label>
                <input type="text" name="phone" value="{{ old('phone') }}">
            </div>
            <div class="field">
                <label>Alamat</label>
                <input type="text" name="address" value="{{ old('address') }}">
            </div>
            <div class="field">
                <label>Termin Pembayaran</label>
                <select name="payment_term" required>
                    <option value="tunai">Tunai</option>
                    <option value="kredit">Kredit</option>
                </select>
            </div>
            <div class="field">
                <label>Jumlah Hari Kredit</label>
                <input type="number" name="payment_term_days" value="{{ old('payment_term_days') }}">
            </div>
            <div class="field">
                <label>Akun COA — Hutang Usaha</label>
                <select name="coa_payable_account_id" required>
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">Simpan Supplier</button>
        </form>
    </div>
@endsection
