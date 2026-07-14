@extends('layouts.app')

@section('title', 'Tambah Jenis Iuran')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 480px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: #A8472F; font-size: 12px; margin-top: 4px; }
    </style>

    <h2>Tambah Jenis Iuran</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.fee-types.store') }}">
            @csrf
            <div class="field"><label>Kode</label><input type="text" name="code" value="{{ old('code') }}" required></div>
            <div class="field"><label>Nama</label><input type="text" name="name" value="{{ old('name') }}" required></div>
            <div class="field">
                <label>Satuan Perhitungan</label>
                <select name="unit_type" required>
                    <option value="flat">Flat (per periode)</option>
                    <option value="per_meter">Per Meter (mis. listrik/air)</option>
                    <option value="per_m2">Per M2</option>
                </select>
            </div>
            <div class="field"><label>Tarif (Rp)</label><input type="number" step="0.01" name="tariff" value="{{ old('tariff') }}" required></div>
            <div class="field">
                <label>Periode Penagihan</label>
                <select name="billing_period" required>
                    <option value="bulanan">Bulanan</option>
                    <option value="mingguan">Mingguan</option>
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Piutang Iuran</label>
                <select name="coa_receivable_account_id" required>
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Pendapatan Iuran</label>
                <select name="coa_revenue_account_id" required>
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary">Simpan</button>
        </form>
    </div>
@endsection
