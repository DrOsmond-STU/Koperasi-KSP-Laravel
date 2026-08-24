@extends('layouts.app')

@section('title', 'Pengajuan Pinjaman')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; max-width: 480px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Pengajuan Pinjaman Baru</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="panel">
        <form method="POST" action="{{ route('staf.pengajuan-pinjaman.simulate') }}">
            @csrf
            <div class="field">
                <label>Anggota</label>
                <select name="member_id" required class="js-searchable">
                    <option value="">— Pilih Anggota —</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}">{{ $member->member_number }} — {{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Produk Pinjaman</label>
                <select name="loan_product_id" required>
                    <option value="">— Pilih Produk —</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} (Rp {{ number_format($product->min_plafon, 0, ',', '.') }}–Rp {{ number_format($product->max_plafon, 0, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Nominal Pinjaman (Rp)</label>
                <input type="number" step="0.01" name="principal_amount" required>
            </div>
            <div class="field">
                <label>Tenor (hari)</label>
                <input type="number" name="tenor_days" required>
            </div>
            <button type="submit" class="btn-primary">Simulasikan Jadwal Angsuran</button>
        </form>
    </div>
@endsection
