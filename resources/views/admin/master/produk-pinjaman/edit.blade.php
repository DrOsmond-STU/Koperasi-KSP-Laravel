@extends('layouts.app')

@section('title', 'Ubah Produk Pinjaman')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 600px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .notice { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 13px 15px; font-size: 13px; line-height: 1.6; margin-bottom: 16px; max-width: 600px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    </style>

    <h2>Ubah Produk Pinjaman</h2>
    <p style="margin-top: -8px;">
        <a href="{{ route('admin.master.loan-products.index') }}" class="btn-link">&larr; Kembali ke Produk Pinjaman</a>
    </p>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="notice">
        Tarif jasa berjalan: <strong>{{ $rate ? number_format((float) $rate->rate_percentage, 3, ',', '.').'% / tahun' : '— belum ada —' }}</strong>
        @if ($rate)
            (berlaku sejak {{ \Illuminate\Support\Carbon::parse($rate->effective_from)->format('d/m/Y') }})
        @endif
        <br>
        Tarif tidak diubah dari layar ini — ia disimpan sebagai riwayat bertanggal supaya angsuran
        yang sudah berjalan tidak ikut berubah.
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.loan-products.update', $product) }}">
            @csrf
            @method('PUT')

            <div class="field"><label>Kode Produk</label><input type="text" name="code" value="{{ old('code', $product->code) }}" required></div>
            <div class="field"><label>Nama Produk</label><input type="text" name="name" value="{{ old('name', $product->name) }}" required></div>

            <div class="grid-2">
                <div class="field"><label>Plafon Minimum (Rp)</label><input type="number" step="0.01" name="min_plafon" value="{{ old('min_plafon', $product->min_plafon) }}" required></div>
                <div class="field"><label>Plafon Maksimum (Rp)</label><input type="number" step="0.01" name="max_plafon" value="{{ old('max_plafon', $product->max_plafon) }}" required></div>
            </div>
            <div class="grid-2">
                <div class="field"><label>Tenor Minimum (bulan)</label><input type="number" name="min_tenor_months" value="{{ old('min_tenor_months', $product->min_tenor_months) }}" required></div>
                <div class="field"><label>Tenor Maksimum (bulan)</label><input type="number" name="max_tenor_months" value="{{ old('max_tenor_months', $product->max_tenor_months) }}" required></div>
            </div>

            <div class="field">
                <label>Metode Perhitungan</label>
                <select name="calculation_method" required>
                    @foreach (['flat' => 'Flat', 'efektif' => 'Efektif (Menurun)', 'anuitas' => 'Anuitas'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('calculation_method', $product->calculation_method) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid-2">
                <div class="field"><label>Biaya Provisi (%)</label><input type="number" step="0.01" name="provision_fee_percentage" value="{{ old('provision_fee_percentage', $product->provision_fee_percentage) }}"></div>
                <div class="field"><label>Denda Keterlambatan (%/hari)</label><input type="number" step="0.001" name="penalty_percentage_per_day" value="{{ old('penalty_percentage_per_day', $product->penalty_percentage_per_day) }}"></div>
            </div>
            <div class="field">
                <label>Ambang Plafon untuk Approval Berjenjang (Rp)</label>
                <input type="number" step="0.01" name="approval_threshold" value="{{ old('approval_threshold', $product->approval_threshold) }}">
                <p class="hint">Pengajuan di atas nominal ini butuh 2 approval berbeda orang; kosongkan bila cukup 1 approval untuk semua nominal.</p>
            </div>

            @foreach ([
                'coa_receivable_account_id' => 'Piutang Pinjaman',
                'coa_interest_income_account_id' => 'Pendapatan Jasa',
                'coa_provision_income_account_id' => 'Pendapatan Provisi',
                'coa_penalty_receivable_account_id' => 'Piutang Denda',
            ] as $nama => $label)
                <div class="field">
                    <label>Akun COA — {{ $label }}</label>
                    <select name="{{ $nama }}" required class="js-searchable">
                        <option value="">— Pilih Akun —</option>
                        @foreach ($postableAccounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old($nama, $product->{$nama}) === $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection
