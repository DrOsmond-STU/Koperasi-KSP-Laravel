@extends('layouts.app')

@section('title', 'Kasir POS')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 760px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: #A8472F; font-size: 12px; margin-top: 4px; }
        .item-row { display: grid; grid-template-columns: 3fr 1fr; gap: 10px; margin-bottom: 8px; }
        .item-row select, .item-row input { padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; }
        .hint { font-size: 11px; color: var(--muted); margin: 8px 0; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-top: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
    </style>

    <h2>Kasir POS</h2>

    @if (session('status'))
        <p style="color:#2E7D52; font-size:13px; margin-bottom:14px;">{{ session('status') }}</p>
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
        <form method="POST" action="{{ route('staf.pos.store') }}">
            @csrf

            <div class="field">
                <label>Cabang</label>
                <select name="branch_id" required>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Metode Bayar</label>
                <select name="payment_method" required>
                    <option value="tunai">Tunai</option>
                    <option value="potong_simpanan">Potong Saldo Simpanan Anggota</option>
                </select>
            </div>
            <div class="field">
                <label>Rekening Simpanan (jika potong saldo)</label>
                <select name="savings_account_id">
                    <option value="">— Tidak Berlaku —</option>
                    @foreach ($savingsAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->account_number }} — {{ $account->member->name }}</option>
                    @endforeach
                </select>
            </div>

            <p class="hint">Isi baris barang yang dibeli. Baris yang dibiarkan kosong akan diabaikan. Harga jual otomatis dari Master Barang.</p>

            @for ($i = 0; $i < 8; $i++)
                <div class="item-row">
                    <select name="items[{{ $i }}][product_id]">
                        <option value="">— Pilih Barang —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }} (Rp {{ number_format((float) $product->selling_price, 0, ',', '.') }})</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.0001" name="items[{{ $i }}][qty]" placeholder="Qty">
                </div>
            @endfor

            <button type="submit" class="btn-primary" style="margin-top: 14px;">Simpan Transaksi</button>
        </form>
    </div>

    <h3>Transaksi Hari Ini</h3>
    <table class="data-table">
        <thead><tr><th>No. Transaksi</th><th>Total</th><th>Metode Bayar</th><th>Struk</th></tr></thead>
        <tbody>
            @forelse ($recentSales as $sale)
                <tr>
                    <td>{{ $sale->sale_number }}</td>
                    <td>Rp {{ number_format((float) $sale->total_amount, 0, ',', '.') }}</td>
                    <td>{{ $sale->payment_method === 'tunai' ? 'Tunai' : 'Potong Simpanan' }}</td>
                    <td><a href="{{ route('staf.pos.receipt', $sale) }}">Lihat Struk</a></td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada transaksi POS hari ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
