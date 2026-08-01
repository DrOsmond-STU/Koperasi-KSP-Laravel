@extends('layouts.app')

@section('title', 'Dashboard RAT')

@section('content')
    <style>
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .branch-picker select { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
        .kpi-card.feature, .kpi-card.tone-pine { background: linear-gradient(160deg, var(--pine), var(--pine-deep)); color: #fff; border: none; }
        .kpi-card.tone-gold { background: linear-gradient(160deg, var(--gold-deep), var(--gold)); color: #fff; border: none; }
        .kpi-card.tone-mix { background: linear-gradient(160deg, var(--pine-deep), var(--gold-deep)); color: #fff; border: none; }
        .kpi-card .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); font-weight: 700; }
        .kpi-card.feature .label, .kpi-card.tone-pine .label, .kpi-card.tone-gold .label, .kpi-card.tone-mix .label { color: rgba(255,255,255,.75); }
        .kpi-card .val { font-size: 21px; font-weight: 800; margin-top: 6px; font-variant-numeric: tabular-nums; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .panel h3 { margin-top: 0; font-size: 14px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        .checklist-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 13px; }
        .check-ok { color: var(--ok); font-weight: 700; }
        .check-bad { color: var(--brick); font-weight: 700; }
        @media (max-width: 980px) { .kpi-grid { grid-template-columns: 1fr; } }

        .trend-bar-label { font-size: 10px; fill: var(--muted); }
        .chart-legend { display: flex; gap: 16px; margin-bottom: 10px; font-size: 12px; flex-wrap: wrap; }
        .chart-legend-item { display: flex; align-items: center; gap: 6px; }
        .chart-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .chart-legend-line { width: 14px; height: 2px; border-radius: 2px; flex-shrink: 0; }
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
        <div class="kpi-card tone-gold" data-detail-target="kpi-detail-pendapatan" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-pendapatan">
            <span class="kpi-icon">@include('partials.icon', ['name' => 'trending-up', 'size' => 20])</span>
            <div class="label">Pendapatan Tahun Berjalan</div>
            <div class="val">Rp {{ number_format($summary['pendapatan'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card tone-mix" data-detail-target="kpi-detail-beban" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-beban">
            <span class="kpi-icon">@include('partials.icon', ['name' => 'credit-card', 'size' => 20])</span>
            <div class="label">Beban Tahun Berjalan</div>
            <div class="val">Rp {{ number_format($summary['beban'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card feature" data-detail-target="kpi-detail-shu-rat" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-shu-rat">
            <span class="kpi-icon">@include('partials.icon', ['name' => 'pie-chart', 'size' => 20])</span>
            <div class="label">Estimasi SHU Tahun Berjalan</div>
            <div class="val">Rp {{ number_format($summary['shu'], 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="kpi-detail-panels">
        <div class="kpi-detail" id="kpi-detail-pendapatan" hidden>
            <h4>Rincian Pendapatan per Bulan — Tahun {{ $summary['year'] }}</h4>
            <table class="data-table">
                <thead><tr><th>Bulan</th><th>Pendapatan</th></tr></thead>
                <tbody>
                    @foreach ($summary['monthly_income_expense'] as $row)
                        <tr><td>{{ \Carbon\Carbon::createFromDate(null, $row['month'], 1)->translatedFormat('F') }}</td><td>Rp {{ number_format($row['pendapatan'], 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="kpi-detail" id="kpi-detail-beban" hidden>
            <h4>Rincian Beban per Bulan — Tahun {{ $summary['year'] }}</h4>
            <table class="data-table">
                <thead><tr><th>Bulan</th><th>Beban</th></tr></thead>
                <tbody>
                    @foreach ($summary['monthly_income_expense'] as $row)
                        <tr><td>{{ \Carbon\Carbon::createFromDate(null, $row['month'], 1)->translatedFormat('F') }}</td><td>Rp {{ number_format($row['beban'], 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="kpi-detail" id="kpi-detail-shu-rat" hidden>
            <h4>Rincian Net (Pendapatan − Beban) per Bulan — Tahun {{ $summary['year'] }}</h4>
            <table class="data-table">
                <thead><tr><th>Bulan</th><th>Net</th></tr></thead>
                <tbody>
                    @foreach ($summary['monthly_income_expense'] as $row)
                        <tr><td>{{ \Carbon\Carbon::createFromDate(null, $row['month'], 1)->translatedFormat('F') }}</td><td>Rp {{ number_format($row['pendapatan'] - $row['beban'], 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h3>Pendapatan vs Beban per Bulan — Tahun {{ $summary['year'] }}</h3>
        @php
            $monthly = $summary['monthly_income_expense'];
            $comboMax = max([1, ...array_column($monthly, 'pendapatan'), ...array_column($monthly, 'beban')]);
            $netValues = array_map(fn ($r) => $r['pendapatan'] - $r['beban'], $monthly);
            $comboMin = min([0, ...$netValues]);
            $comboRange = max(1, $comboMax - $comboMin);
            $groupW = 40; $miniBarW = 15; $groupGap = 14; $comboH = 150;
            $linePoints = [];
            $pendapatanTotal = array_sum(array_column($monthly, 'pendapatan'));
            $bebanTotal = array_sum(array_column($monthly, 'beban'));
            $netTotal = $pendapatanTotal - $bebanTotal;
        @endphp
        <div class="chart-wrap">
            <div class="chart-legend">
                <span class="chart-legend-item" data-series="pendapatan" tabindex="0"
                    data-tooltip="{{ \App\Support\DashboardChart::tooltip('Total Tahun '.$summary['year'], [['label' => 'Pendapatan', 'value' => \App\Support\DashboardChart::money($pendapatanTotal), 'color' => 'var(--gold)', 'strong' => true]]) }}">
                    <span class="chart-legend-dot" style="background: var(--gold);"></span> Pendapatan
                </span>
                <span class="chart-legend-item" data-series="beban" tabindex="0"
                    data-tooltip="{{ \App\Support\DashboardChart::tooltip('Total Tahun '.$summary['year'], [['label' => 'Beban', 'value' => \App\Support\DashboardChart::money($bebanTotal), 'color' => 'var(--brick)', 'strong' => true]]) }}">
                    <span class="chart-legend-dot" style="background: var(--brick);"></span> Beban
                </span>
                <span class="chart-legend-item" data-series="net" tabindex="0"
                    data-tooltip="{{ \App\Support\DashboardChart::tooltip('Total Tahun '.$summary['year'], [['label' => 'Net (Pendapatan − Beban)', 'value' => \App\Support\DashboardChart::money($netTotal), 'color' => 'var(--pine)', 'strong' => true]]) }}">
                    <span class="chart-legend-line" style="background: var(--pine);"></span> Net (Pendapatan − Beban)
                </span>
            </div>
            <svg viewBox="0 0 {{ count($monthly) * ($groupW + $groupGap) }} {{ $comboH + 30 }}" style="width:100%; height:210px;" role="img" aria-label="Grafik pendapatan vs beban per bulan tahun {{ $summary['year'] }}">
                @foreach ($monthly as $i => $row)
                    @php
                        $x = $i * ($groupW + $groupGap);
                        $hP = $row['pendapatan'] > 0 ? max(2, ($row['pendapatan'] / $comboMax) * $comboH) : 0;
                        $hB = $row['beban'] > 0 ? max(2, ($row['beban'] / $comboMax) * $comboH) : 0;
                        $net = $row['pendapatan'] - $row['beban'];
                        $lineY = $comboH - (($net - $comboMin) / $comboRange) * $comboH;
                        $linePoints[] = [$x + $groupW / 2, $lineY];
                        $monthLabel = \Carbon\Carbon::createFromDate(null, $row['month'], 1)->translatedFormat('F Y');
                    @endphp
                    <rect class="act-mark" data-series="pendapatan" tabindex="0"
                        data-tooltip="{{ \App\Support\DashboardChart::tooltip($monthLabel, [['label' => 'Pendapatan', 'value' => \App\Support\DashboardChart::money($row['pendapatan']), 'color' => 'var(--gold)', 'strong' => true]]) }}"
                        x="{{ $x }}" y="{{ $comboH - $hP }}" width="{{ $miniBarW }}" height="{{ $hP }}" rx="2" fill="var(--gold)"></rect>
                    <rect class="act-mark" data-series="beban" tabindex="0"
                        data-tooltip="{{ \App\Support\DashboardChart::tooltip($monthLabel, [['label' => 'Beban', 'value' => \App\Support\DashboardChart::money($row['beban']), 'color' => 'var(--brick)', 'strong' => true]]) }}"
                        x="{{ $x + $miniBarW + 4 }}" y="{{ $comboH - $hB }}" width="{{ $miniBarW }}" height="{{ $hB }}" rx="2" fill="var(--brick)"></rect>
                    <text x="{{ $x + $groupW / 2 }}" y="{{ $comboH + 16 }}" text-anchor="middle" class="trend-bar-label">{{ \Carbon\Carbon::createFromDate(null, $row['month'], 1)->translatedFormat('M') }}</text>
                @endforeach
                <path d="{{ \App\Support\DashboardChart::smoothPath($linePoints) }}" fill="none" stroke="var(--pine)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                @foreach ($monthly as $i => $row)
                    @php
                        [$px, $py] = $linePoints[$i];
                        $net = $row['pendapatan'] - $row['beban'];
                        $monthLabel = \Carbon\Carbon::createFromDate(null, $row['month'], 1)->translatedFormat('F Y');
                    @endphp
                    <circle class="act-mark" data-series="net" tabindex="0"
                        data-tooltip="{{ \App\Support\DashboardChart::tooltip($monthLabel, [
                            ['label' => 'Pendapatan', 'value' => \App\Support\DashboardChart::money($row['pendapatan']), 'color' => 'var(--gold)'],
                            ['label' => 'Beban', 'value' => \App\Support\DashboardChart::money($row['beban']), 'color' => 'var(--brick)'],
                            ['label' => 'Net', 'value' => \App\Support\DashboardChart::money($net), 'color' => 'var(--pine)', 'strong' => true],
                        ]) }}"
                        cx="{{ $px }}" cy="{{ $py }}" r="3.5" fill="var(--pine)"></circle>
                @endforeach
            </svg>
            <div class="chart-tooltip" role="status" aria-live="polite"></div>
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
            @php
                $growthByMonth = $summary['member_growth']->keyBy('month');
                $growthMax = max([1, ...$summary['member_growth']->pluck('total')->all()]);
                $barWidth = 44; $gap = 14; $chartHeight = 120;
            @endphp
            <div class="chart-wrap">
                <svg viewBox="0 0 {{ 12 * ($barWidth + $gap) }} {{ $chartHeight + 30 }}" style="width:100%; height:170px;" role="img" aria-label="Grafik perkembangan anggota baru per bulan tahun {{ $summary['year'] }}">
                    @for ($m = 1; $m <= 12; $m++)
                        @php
                            $total = $growthByMonth->get($m)?->total ?? 0;
                            $height = $total > 0 ? max(4, ($total / $growthMax) * $chartHeight) : 2;
                            $x = ($m - 1) * ($barWidth + $gap);
                            $y = $chartHeight - $height;
                            $monthLabel = \Carbon\Carbon::createFromDate(null, $m, 1)->translatedFormat('F Y');
                        @endphp
                        <rect class="act-mark" data-series="anggota" tabindex="0"
                            data-tooltip="{{ \App\Support\DashboardChart::tooltip($monthLabel, [['label' => 'Anggota Baru', 'value' => number_format($total, 0, ',', '.'), 'color' => 'var(--pine)', 'strong' => true]]) }}"
                            x="{{ $x }}" y="{{ $y }}" width="{{ $barWidth }}" height="{{ $height }}" rx="4" fill="var(--pine)"></rect>
                        <text x="{{ $x + $barWidth / 2 }}" y="{{ $chartHeight + 16 }}" text-anchor="middle" class="trend-bar-label">{{ \Carbon\Carbon::createFromDate(null, $m, 1)->translatedFormat('M') }}</text>
                    @endfor
                </svg>
                <div class="chart-tooltip" role="status" aria-live="polite"></div>
            </div>
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
