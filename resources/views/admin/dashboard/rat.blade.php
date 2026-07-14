@extends('layouts.app')

@section('title', 'Dashboard RAT')

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
        .checklist-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 13px; }
        .check-ok { color: #2E7D52; font-weight: 700; }
        .check-bad { color: #A8472F; font-weight: 700; }
        @media (max-width: 980px) { .kpi-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="dash-header">
        <h2>Dashboard RAT — Tahun Buku {{ $summary['year'] }} {{ $isConsolidated ? '(Konsolidasi)' : '' }}</h2>
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
        <div class="kpi-card">
            <div class="label">Pendapatan Tahun Berjalan</div>
            <div class="val">Rp {{ number_format($summary['pendapatan'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Beban Tahun Berjalan</div>
            <div class="val">Rp {{ number_format($summary['beban'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card feature">
            <div class="label">Estimasi SHU Tahun Berjalan</div>
            <div class="val">Rp {{ number_format($summary['shu'], 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="panel">
        <h3>Checklist Kelengkapan RAT</h3>
        @foreach ($summary['readiness_checklist'] as $label => $isReady)
            <div class="checklist-item">
                <span class="{{ $isReady ? 'check-ok' : 'check-bad' }}">{{ $isReady ? '✓' : '✗' }}</span>
                <span>{{ $label }}</span>
            </div>
        @endforeach
    </div>

    <div class="panel">
        <h3>Perkembangan Anggota Tahun {{ $summary['year'] }} (Total saat ini: {{ $summary['total_members'] }})</h3>
        @if ($summary['member_growth']->isEmpty())
            <p style="color: var(--muted); font-size: 13px;">Belum ada anggota baru bergabung tahun ini.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Bulan</th><th>Anggota Baru</th></tr></thead>
                <tbody>
                    @foreach ($summary['member_growth'] as $row)
                        <tr><td>{{ \Carbon\Carbon::createFromDate(null, $row->month, 1)->translatedFormat('F') }}</td><td>{{ $row->total }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <p style="color: var(--muted); font-size: 12px;">Simulasi alokasi SHU (dana cadangan/jasa anggota/dst) dan status persetujuan Pengawas menyusul di Fase 5 setelah Modul SHU dibangun (lihat 01_PRD.md §5.4).</p>
@endsection
