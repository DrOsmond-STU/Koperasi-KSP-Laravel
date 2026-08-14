@extends('layouts.app')

@section('title', 'Ubah Produk Simpanan')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .notice { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 13px 15px; font-size: 13px; line-height: 1.6; margin-bottom: 16px; max-width: 560px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    </style>

    <h2>Ubah Produk Simpanan</h2>
    <p style="margin-top: -8px;">
        <a href="{{ route('admin.master.savings-products.index') }}" class="btn-link">&larr; Kembali ke Produk Simpanan</a>
    </p>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="notice">
        Tarif jasa berjalan: <strong>{{ $rate ? number_format((float) $rate->rate_percentage, 3, ',', '.').'%' : '— belum ada —' }}</strong>
        @if ($rate)
            (berlaku sejak {{ \Illuminate\Support\Carbon::parse($rate->effective_from)->format('d/m/Y') }})
        @endif
        <br>
        Tarif tidak diubah dari layar ini — ia disimpan sebagai riwayat bertanggal supaya perhitungan
        transaksi yang sudah lewat tidak ikut berubah.
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.savings-products.update', $product) }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label>Kode Produk</label>
                <input type="text" name="code" value="{{ old('code', $product->code) }}" required>
            </div>
            <div class="field">
                <label>Nama Produk</label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required>
            </div>
            <div class="field">
                <label>Kategori</label>
                <select name="category" required>
                    @forelse ($categories as $category)
                        <option value="{{ $category->code }}" @selected(old('category', $product->category) === $category->code)>{{ $category->name }}</option>
                    @empty
                        <option value="">— belum ada kategori —</option>
                    @endforelse
                </select>
                @if ($categories->isEmpty())
                    <p class="hint">Belum ada kategori aktif. <a href="{{ route('admin.master.savings-product-categories.index') }}">Tambahkan kategori dulu</a>.</p>
                @endif
            </div>
            <div class="field">
                <label>Metode Perhitungan Jasa</label>
                <select name="interest_method" required>
                    @foreach (['flat' => 'Flat', 'saldo_harian' => 'Saldo Harian', 'saldo_rata_rata_bulanan' => 'Saldo Rata-rata Bulanan', 'tiered' => 'Tiered (bertingkat)'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('interest_method', $product->interest_method) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label>Setoran Awal Minimum</label>
                    <input type="number" step="0.01" name="minimum_initial_deposit" value="{{ old('minimum_initial_deposit', $product->minimum_initial_deposit) }}">
                </div>
                <div class="field">
                    <label>Setoran Berikutnya Minimum</label>
                    <input type="number" step="0.01" name="minimum_subsequent_deposit" value="{{ old('minimum_subsequent_deposit', $product->minimum_subsequent_deposit) }}">
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label>Biaya Administrasi</label>
                    <input type="number" step="0.01" name="admin_fee" value="{{ old('admin_fee', $product->admin_fee) }}">
                </div>
                <div class="field">
                    <label>Periode Biaya Administrasi</label>
                    <select name="admin_fee_period">
                        <option value="">— tidak ada —</option>
                        @foreach (['bulanan' => 'Bulanan', 'tahunan' => 'Tahunan'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('admin_fee_period', $product->admin_fee_period) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="field">
                <label>Denda Penarikan (%)</label>
                <input type="number" step="0.01" name="withdrawal_penalty_percentage" value="{{ old('withdrawal_penalty_percentage', $product->withdrawal_penalty_percentage) }}">
            </div>

            <div class="field">
                <label>Akun COA — Kewajiban Simpanan</label>
                <select name="coa_liability_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('coa_liability_account_id', $product->coa_liability_account_id) === $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Beban Jasa Simpanan</label>
                <select name="coa_interest_expense_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('coa_interest_expense_account_id', $product->coa_interest_expense_account_id) === $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection
