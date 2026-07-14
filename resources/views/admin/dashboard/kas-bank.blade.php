@extends('layouts.app')

@section('title', 'Dashboard Kas/Bank')

@section('content')
    <style>
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .branch-picker select { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
        .kpi-card.feature { background: linear-gradient(160deg, var(--pine), var(--pine-deep)); color: #fff; border: none; }
        .kpi-card .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); font-weight: 700; }
        .kpi-card.feature .label { color: rgba(255,255,255,.75); }
        .kpi-card .val { font-size: 21px; font-weight: 800; margin-top: 6px; font-variant-numeric: tabular-nums; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        .empty-note { color: var(--muted); font-size: 13px; }
        @media (max-width: 980px) { .kpi-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="dash-header">
        <h2>Dashboard Kas/Bank {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>
        <form class="branch-picker" method="GET">
            <select name="branch_id" onchange="this.form.submit()">
                @if (is_null($selectedBranchId) || $branches->count() > 1)
                    <option value="" {{ $isConsolidated ? 'selected' : '' }}>
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
        <div class="kpi-card feature">
            <div class="label">Total Saldo Kas &amp; Bank</div>
            <div class="val">Rp {{ number_format($summary['total_balance'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Masuk Hari Ini</div>
            <div class="val">Rp {{ number_format($summary['today_in'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Keluar Hari Ini</div>
            <div class="val">Rp {{ number_format($summary['today_out'], 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="panel">
        <h3>Saldo per Akun Kas/Bank</h3>
        <table class="data-table">
            <thead><tr><th>Akun</th><th>Saldo</th></tr></thead>
            <tbody>
                @foreach ($summary['balances'] as $row)
                    <tr>
                        <td>{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                        <td>Rp {{ number_format($row['balance'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h3>Breakdown Kas Masuk/Keluar per Kategori (Hari Ini)</h3>
        @if (count($summary['category_breakdown']) === 0)
            <p class="empty-note">Belum ada transaksi kas Teller hari ini.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Kategori</th><th>Jenis</th><th>Total</th></tr></thead>
                <tbody>
                    @foreach ($summary['category_breakdown'] as $row)
                        <tr>
                            <td>{{ $row['category_name'] }}</td>
                            <td>{{ ucfirst($row['type']) }}</td>
                            <td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="panel">
        <h3>Transaksi Terbaru</h3>
        @if ($summary['recent_entries']->isEmpty())
            <p class="empty-note">Belum ada transaksi kas/bank pada cabang ini.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Tanggal</th><th>Keterangan</th></tr></thead>
                <tbody>
                    @foreach ($summary['recent_entries'] as $entry)
                        <tr>
                            <td>{{ $entry->entry_date->translatedFormat('d M Y') }}</td>
                            <td>{{ $entry->description }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
