@extends('layouts.app')

@section('title', 'Buka Rekening Simpanan')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; max-width: 640px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .product-row { border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .product-row input[type="checkbox"] { width: auto; flex: none; }
        .product-info { flex: 1; }
        .product-name { font-weight: 600; font-size: 13px; }
        .product-hint { font-size: 11px; color: var(--muted); }
        .product-row input[type="number"] { width: 160px; box-sizing: border-box; padding: 7px 10px; border: 1px solid var(--line); border-radius: 7px; }
        .field-errors { color: var(--brick); font-size: 11px; margin: 4px 0 0; }
    </style>

    <h2>Buka Rekening Simpanan — Setoran Awal</h2>
    <p class="hint" style="color: var(--muted); font-size: 13px; margin-top: -8px;">
        Untuk anggota yang belum punya rekening simpanan sama sekali. Pilih satu atau beberapa
        produk sekaligus (mis. Simpanan Pokok + Simpanan Wajib) — kalau ada setoran awal,
        langsung diposting sebagai transaksi kas masuk.
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <div class="panel">
        <form method="POST" action="{{ route('staf.teller.buka-rekening.store') }}">
            @csrf
            <div class="field">
                <label>Anggota</label>
                <select name="member_id" required class="js-searchable">
                    <option value="">— Pilih Anggota —</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected(old('member_id') == $member->id)>
                            {{ $member->member_number }} — {{ $member->name }}
                        </option>
                    @endforeach
                </select>
                @error('member_id')
                    <p class="field-errors">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label>Produk Simpanan yang Dibuka</label>
                @forelse ($products as $product)
                    <div class="product-row">
                        <input type="checkbox" name="product_ids[]" value="{{ $product->id }}"
                               id="product-{{ $product->id }}"
                               @checked(in_array($product->id, (array) old('product_ids', [])))>
                        <div class="product-info">
                            <label for="product-{{ $product->id }}" class="product-name">
                                {{ $product->name }} ({{ ucfirst($product->category) }})
                            </label>
                            <div class="product-hint">
                                Minimal setoran awal: Rp {{ number_format($product->minimum_initial_deposit, 0, ',', '.') }}
                            </div>
                            @error("initial_deposits.{$product->id}")
                                <p class="field-errors">{{ $message }}</p>
                            @enderror
                        </div>
                        <input type="number" step="0.01" min="0"
                               name="initial_deposits[{{ $product->id }}]"
                               value="{{ old('initial_deposits.'.$product->id, (float) $product->minimum_initial_deposit) }}"
                               placeholder="Setoran awal (Rp)">
                    </div>
                @empty
                    <p style="color: var(--muted); font-size: 13px;">
                        Belum ada produk simpanan aktif. Tambahkan dulu di menu Master &raquo; Produk Simpanan.
                    </p>
                @endforelse
                @error('product_ids')
                    <p class="field-errors">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary">Buka Rekening</button>
            <a href="{{ route('staf.teller.create') }}" style="margin-left: 12px; font-size: 13px;">Kembali ke Teller</a>
        </form>
    </div>
@endsection
