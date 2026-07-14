@extends('layouts.app')

@section('title', 'Layanan Teller')

@section('content')
    <style>
        .teller-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
        .feed-item { padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 13px; display: flex; justify-content: space-between; }
        @media (max-width: 980px) { .teller-grid { grid-template-columns: 1fr; } }
    </style>

    <h2>Layanan Teller — Simpanan</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="teller-grid">
        <div class="panel">
            <h3>Transaksi Simpanan</h3>
            <form method="POST" action="{{ route('staf.teller.preview') }}">
                @csrf
                <div class="field">
                    <label>Rekening</label>
                    <select name="savings_account_id" required>
                        <option value="">— Pilih Rekening —</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->account_number }} — {{ $account->member->name }} ({{ $account->savingsProduct->name }}) — Saldo Rp {{ number_format($account->balance, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Jenis Transaksi</label>
                    <select name="type" required>
                        <option value="setor">Setor</option>
                        <option value="tarik">Tarik</option>
                    </select>
                </div>
                <div class="field">
                    <label>Nominal (Rp)</label>
                    <input type="number" step="0.01" name="amount" required>
                </div>
                <div class="field">
                    <label>Keterangan (opsional)</label>
                    <input type="text" name="description">
                </div>
                <button type="submit" class="btn-primary">Lihat Preview Jurnal</button>
            </form>
        </div>

        <div class="panel">
            <h3>Transaksi Hari Ini</h3>
            @forelse ($recentTransactions as $trx)
                <div class="feed-item">
                    <span>{{ $trx->savingsAccount->member->name }} — {{ ucfirst($trx->type) }}</span>
                    <span>Rp {{ number_format($trx->amount, 0, ',', '.') }}</span>
                </div>
            @empty
                <p style="color: var(--muted); font-size: 13px;">Belum ada transaksi pada cabang ini hari ini.</p>
            @endforelse
        </div>
    </div>
@endsection
