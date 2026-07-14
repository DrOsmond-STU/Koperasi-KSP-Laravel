@extends('layouts.app')

@section('title', 'Unit Pengelola Fasilitas (UPF)')

@section('content')
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 9px 16px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; font-size: 13px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
        .banner-bad { background: #F2DED7; color: #A8472F; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        @media (max-width: 980px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>

    <h2>Unit Pengelola Fasilitas — Tagihan &amp; Pembayaran Iuran</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <div class="banner-bad">{{ session('error') }}</div>
    @endif

    <div class="grid-2">
        <div class="panel">
            <h3>Buat Tagihan</h3>
            <form method="POST" action="{{ route('staf.upf.tagihan') }}">
                @csrf
                <div class="field">
                    <label>Kios</label>
                    <select name="tenant_id" required>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->code }} — {{ $tenant->owner_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Jenis Iuran</label>
                    <select name="fee_type_id" required>
                        @foreach ($feeTypes as $feeType)
                            <option value="{{ $feeType->id }}">{{ $feeType->name }} ({{ $feeType->unit_type }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Periode (YYYY-MM)</label>
                    <input type="text" name="period" placeholder="2026-01" required>
                </div>
                <div class="field">
                    <label>Meter Awal (jika berbasis meteran)</label>
                    <input type="number" step="0.01" name="meter_start">
                </div>
                <div class="field">
                    <label>Meter Akhir (jika berbasis meteran)</label>
                    <input type="number" step="0.01" name="meter_end">
                </div>
                <button type="submit" class="btn-primary">Buat Tagihan</button>
            </form>
        </div>

        <div class="panel">
            <h3>Catat Pembayaran</h3>
            <form method="POST" action="{{ route('staf.upf.pembayaran') }}">
                @csrf
                <div class="field">
                    <label>Tagihan</label>
                    <select name="fee_billing_id" required>
                        @foreach ($outstandingBillings as $billing)
                            <option value="{{ $billing->id }}">
                                {{ $billing->tenant->code }} — {{ $billing->feeType->name }} ({{ $billing->period }}) — Sisa Rp {{ number_format($billing->remainingAmount(), 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Nominal Bayar (Rp)</label>
                    <input type="number" step="0.01" name="amount" required>
                </div>
                <button type="submit" class="btn-primary">Catat Pembayaran</button>
            </form>
        </div>
    </div>

    <div class="panel">
        <h3>Tunggakan Iuran</h3>
        @if ($outstandingBillings->isEmpty())
            <p style="color: var(--muted); font-size: 13px;">Tidak ada tunggakan iuran.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Kios</th><th>Jenis Iuran</th><th>Periode</th><th>Tagihan</th><th>Sisa</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($outstandingBillings as $billing)
                        <tr>
                            <td>{{ $billing->tenant->code }} — {{ $billing->tenant->owner_name }}</td>
                            <td>{{ $billing->feeType->name }}</td>
                            <td>{{ $billing->period }}</td>
                            <td>Rp {{ number_format($billing->amount, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($billing->remainingAmount(), 0, ',', '.') }}</td>
                            <td>{{ $billing->status === 'sebagian' ? 'Sebagian' : 'Belum Bayar' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
