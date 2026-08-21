@extends('layouts.app')

@section('title', 'Ubah Produk Pinjaman')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 600px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-secondary { display: inline-block; padding: 10px 18px; background: var(--paper); color: var(--pine); border: 1px solid var(--line); border-radius: 9px; text-decoration: none; font-weight: 700; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .checkbox-row { display: flex; align-items: center; gap: 8px; }
        .checkbox-row input[type=checkbox] { width: auto; margin: 0; }
        .info-box { background: var(--paper); border: 1px solid var(--line); border-radius: 9px; padding: 10px 12px; font-size: 12px; color: var(--muted); margin-bottom: 12px; }
    </style>

    <h2>Ubah Produk Pinjaman</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

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
                <div class="field"><label>Tenor Minimum (hari)</label><input type="number" name="min_tenor_days" value="{{ old('min_tenor_days', $product->min_tenor_days) }}" required></div>
                <div class="field"><label>Tenor Maksimum (hari)</label><input type="number" name="max_tenor_days" value="{{ old('max_tenor_days', $product->max_tenor_days) }}" required></div>
            </div>

            <div class="field">
                <label>Metode Perhitungan</label>
                <select name="calculation_method" required>
                    @foreach (['flat' => 'Flat', 'efektif' => 'Efektif (Menurun)', 'anuitas' => 'Anuitas'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('calculation_method', $product->calculation_method) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tarif jasa ditampilkan read-only di sini; perubahannya
                 dilakukan lewat menu Tarif Parameter agar versi-per-tanggal
                 (efektif kapan berlaku) tetap terjaga secara non-retroaktif. --}}
            <div class="info-box">
                <strong>Tarif Jasa Berlaku:</strong>
                @if ($currentRate)
                    {{ number_format($currentRate->rate_percentage, 3, ',', '.') }}% /tahun
                    <span style="opacity: 0.7;">(efektif sejak {{ $currentRate->effective_from->translatedFormat('d M Y') }})</span>
                @else
                    <em>belum ada tarif</em>
                @endif
                <br>
                Untuk mengubah tarif, buka menu <strong>Tarif Parameter</strong> — perubahan
                tarif dicatat sebagai riwayat versi (non-retroaktif) sesuai PRD §7.2.
            </div>

            <div class="field"><label>Biaya Provisi (%)</label><input type="number" step="0.01" name="provision_fee_percentage" value="{{ old('provision_fee_percentage', $product->provision_fee_percentage) }}"></div>
            <div class="field"><label>Denda Keterlambatan (%/hari)</label><input type="number" step="0.001" name="penalty_percentage_per_day" value="{{ old('penalty_percentage_per_day', $product->penalty_percentage_per_day) }}"></div>
            <div class="field">
                <label>Ambang Plafon untuk Approval Berjenjang (Rp)</label>
                <input type="number" step="0.01" name="approval_threshold" value="{{ old('approval_threshold', $product->approval_threshold) }}">
                <p class="hint">Pengajuan di atas nominal ini butuh 2 approval berbeda orang; kosongkan bila cukup 1 approval untuk semua nominal.</p>
            </div>

            <div class="field">
                <label>Akun COA — Piutang Pinjaman</label>
                <select name="coa_receivable_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('coa_receivable_account_id', $product->coa_receivable_account_id) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Pendapatan Jasa</label>
                <select name="coa_interest_income_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('coa_interest_income_account_id', $product->coa_interest_income_account_id) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Pendapatan Provisi</label>
                <select name="coa_provision_income_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('coa_provision_income_account_id', $product->coa_provision_income_account_id) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Akun COA — Piutang Denda</label>
                <select name="coa_penalty_receivable_account_id" required class="js-searchable">
                    <option value="">— Pilih Akun —</option>
                    @foreach ($postableAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('coa_penalty_receivable_account_id', $product->coa_penalty_receivable_account_id) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field checkbox-row">
                <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                <label for="is_active" style="margin: 0;">Aktif — tampilkan pada daftar produk saat pengajuan pinjaman baru</label>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 18px;">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.master.loan-products.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
