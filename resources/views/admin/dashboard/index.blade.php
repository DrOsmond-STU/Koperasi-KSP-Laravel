@extends('layouts.app')

@section('title', 'Dashboard Utama')

@section('content')
    <style>
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .branch-picker select { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
        .kpi-card.feature { background: linear-gradient(160deg, var(--pine), var(--pine-deep)); color: #fff; border: none; }
        .kpi-card .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); font-weight: 700; }
        .kpi-card.feature .label { color: rgba(255,255,255,.75); }
        .kpi-card .val { font-size: 21px; font-weight: 800; margin-top: 6px; font-variant-numeric: tabular-nums; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        .empty-note { color: var(--muted); font-size: 13px; }
        @media (max-width: 980px) { .kpi-grid { grid-template-columns: 1fr 1fr; } }
    </style>

    <div class="dash-header">
        <h2>Dashboard Utama {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>
        <form class="branch-picker" method="GET">
            <select name="branch_id" onchange="this.form.submit()">
                @if (is_null($selectedBranchId) || $branches->count() > 1)
                    <option value="" {{ $isConsolidated ? 'selected' : '' }} {{ $branches->count() <= 1 && !$isConsolidated ? 'disabled' : '' }}>
                        {{ $branches->count() > 1 ? 'Semua Cabang (Konsolidasi)' : '' }}
                    </option>
                @endif
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" {{ $selectedBranchId === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="label">Total Anggota</div>
            <div class="val">{{ number_format($summary['total_members'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Total Simpanan</div>
            <div class="val">Rp {{ number_format($summary['total_savings'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Pinjaman Outstanding</div>
            <div class="val">Rp {{ number_format($summary['total_loan_outstanding'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card feature">
            <div class="label">SHU Berjalan</div>
            <div class="val">Rp {{ number_format($summary['shu_running'], 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="panel">
        <h3>Rasio NPL (Pinjaman Bermasalah)</h3>
        <p style="font-size: 24px; font-weight: 800; margin: 8px 0;">{{ $summary['npl_ratio'] }}%</p>
    </div>

    <div class="panel">
        <h3>Anggota per Jenis</h3>
        @if ($summary['members_by_type']->isEmpty())
            <p class="empty-note">Belum ada anggota pada cabang/periode ini.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Jenis Anggota</th><th>Jumlah</th></tr></thead>
                <tbody>
                    @foreach ($summary['members_by_type'] as $row)
                        <tr><td>{{ $row->type_name }}</td><td>{{ $row->total }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="panel">
        <h3>Simpanan per Produk</h3>
        @if ($summary['savings_by_product']->isEmpty())
            <p class="empty-note">Belum ada transaksi simpanan pada cabang/periode ini.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Produk</th><th>Total Saldo</th></tr></thead>
                <tbody>
                    @foreach ($summary['savings_by_product'] as $row)
                        <tr><td>{{ $row->product_name }}</td><td>Rp {{ number_format($row->total, 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
