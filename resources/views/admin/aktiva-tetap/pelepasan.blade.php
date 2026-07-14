@extends('layouts.app')

@section('title', 'Pelepasan Aktiva Tetap')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: #A8472F; font-size: 12px; margin-top: 4px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Pelepasan Aktiva Tetap</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-text" style="margin-bottom:14px;">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.aktiva-tetap.pelepasan.store') }}">
            @csrf

            <div class="field">
                <label>Aktiva Tetap</label>
                <select name="fixed_asset_id" required>
                    <option value="">— Pilih Aset —</option>
                    @foreach ($assets as $asset)
                        <option value="{{ $asset->id }}">{{ $asset->code }} — {{ $asset->name }} (Nilai Buku: Rp {{ number_format((float) $asset->bookValue(), 0, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Jenis Pelepasan</label>
                <select name="disposal_type" required>
                    <option value="dijual">Dijual</option>
                    <option value="dihapusbukukan">Dihapusbukukan</option>
                    <option value="hilang">Hilang</option>
                </select>
            </div>
            <div class="field">
                <label>Nilai Jual (jika Dijual)</label>
                <input type="number" step="0.01" name="sale_amount">
            </div>

            <button type="submit" class="btn-primary">Proses Pelepasan</button>
        </form>
    </div>
@endsection
