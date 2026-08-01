@extends('layouts.portal')

@section('title', 'Pengajuan Pinjaman')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 480px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-top: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .empty-note { color: var(--muted); font-size: 13px; }
    </style>

    <h2>Pengajuan Pinjaman</h2>

    @if (session('status'))
        <p style="color: var(--ok); font-size: 13px; margin-bottom: 14px;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('portal.loan-application.store') }}">
            @csrf
            <div class="field">
                <label>Produk Pinjaman</label>
                <select name="loan_product_id" required>
                    <option value="">— Pilih Produk —</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('loan_product_id') == $product->id)>
                            {{ $product->name }} (Rp {{ number_format($product->min_plafon, 0, ',', '.') }} – Rp {{ number_format($product->max_plafon, 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Jumlah Pinjaman (Rp)</label>
                <input type="number" step="0.01" name="principal_amount" value="{{ old('principal_amount') }}" required>
            </div>
            <div class="field">
                <label>Tenor (bulan)</label>
                <input type="number" name="tenor_months" value="{{ old('tenor_months') }}" required>
            </div>
            <button type="submit" class="btn-primary">Kirim Pengajuan</button>
        </form>
    </div>

    <h3 style="margin-top:28px;">Pengajuan Terakhir</h3>
    <table class="data-table">
        <thead><tr><th>No. Pinjaman</th><th>Plafon</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($recentApplications as $loan)
                <tr>
                    <td>{{ $loan->loan_number }}</td>
                    <td>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                    <td>{{ ucfirst($loan->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="3"><p class="empty-note">Belum ada pengajuan pinjaman.</p></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
