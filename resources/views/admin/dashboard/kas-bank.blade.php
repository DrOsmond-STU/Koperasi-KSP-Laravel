@extends('layouts.app')

@section('title', 'Dashboard Kas/Bank')

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
        .empty-note { color: var(--muted); font-size: 13px; }
        @media (max-width: 980px) { .kpi-grid { grid-template-columns: 1fr; } }

        .breakdown-row { margin-bottom: 10px; }
        .breakdown-row .breakdown-label { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
        .breakdown-track { height: 10px; background: var(--paper); border-radius: 6px; overflow: hidden; }
        .breakdown-fill { height: 100%; border-radius: 6px; }
        .type-tag { font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 10px; margin-left: 6px; }
        .type-tag.masuk { background: var(--leaf); color: var(--ok); }
        .type-tag.keluar { background: var(--brick-soft); color: var(--brick); }
        .trend-bar-label { font-size: 10px; fill: var(--muted); }
        .chart-legend { display: flex; gap: 16px; margin-bottom: 10px; font-size: 12px; flex-wrap: wrap; }
        .chart-legend-item { display: flex; align-items: center; gap: 6px; }
        .chart-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .chart-legend-line { width: 14px; height: 2px; border-radius: 2px; flex-shrink: 0; }
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

    <form method="GET" action="{{ route('admin.dashboard.kas-bank.print') }}" target="_blank" style="display:flex; gap:8px; align-items:center; margin-bottom:16px;">
        @if ($selectedBranchId)
            <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
        @endif
        <label for="tanggal" style="font-size:13px; color:var(--muted);">Cetak Laporan Harian:</label>
        <input type="date" id="tanggal" name="tanggal" value="{{ now()->toDateString() }}" style="padding:6px 10px; border:1px solid var(--line); border-radius:8px;">
        <button type="submit" class="btn-secondary">Cetak</button>
    </form>

    <div class="kpi-grid">
        <div class="kpi-card feature" data-detail-target="kpi-detail-saldo" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-saldo">
            <span class="kpi-icon">@include('partials.icon', ['name' => 'wallet', 'size' => 20])</span>
            <div class="label">Total Saldo Kas &amp; Bank</div>
            <div class="val">Rp {{ number_format($summary['total_balance'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card tone-gold" data-detail-target="kpi-detail-masuk" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-masuk">
            <span class="kpi-icon">@include('partials.icon', ['name' => 'trending-up', 'size' => 20])</span>
            <div class="label">Masuk Hari Ini</div>
            <div class="val">Rp {{ number_format($summary['today_in'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card tone-mix" data-detail-target="kpi-detail-keluar" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-keluar">
            <span class="kpi-icon">@include('partials.icon', ['name' => 'log-out', 'size' => 20])</span>
            <div class="label">Keluar Hari Ini</div>
            <div class="val">Rp {{ number_format($summary['today_out'], 0, ',', '.') }}</div>
        </div>
    </div>

    @php
        $masukCategories = array_filter($summary['category_breakdown'], fn ($row) => $row['type'] === 'masuk');
        $keluarCategories = array_filter($summary['category_breakdown'], fn ($row) => $row['type'] === 'keluar');
    @endphp
    <div class="kpi-detail-panels">
        <div class="kpi-detail" id="kpi-detail-saldo" hidden>
            <h4>Rincian Saldo per Akun Kas/Bank</h4>
            <table class="data-table">
                <thead><tr><th>Akun</th><th>Saldo</th></tr></thead>
                <tbody>
                    @foreach ($summary['balances'] as $row)
                        <tr><td>{{ $row['account']->code }} — {{ $row['account']->name }}</td><td>Rp {{ number_format($row['balance'], 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="kpi-detail" id="kpi-detail-masuk" hidden>
            <h4>Rincian Kas Masuk Hari Ini per Kategori</h4>
            @if (count($masukCategories) === 0)
                <p class="empty-note">Belum ada kas masuk hari ini.</p>
            @else
                <table class="data-table">
                    <thead><tr><th>Kategori</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach ($masukCategories as $row)
                            <tr><td>{{ $row['category_name'] }}</td><td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        <div class="kpi-detail" id="kpi-detail-keluar" hidden>
            <h4>Rincian Kas Keluar Hari Ini per Kategori</h4>
            @if (count($keluarCategories) === 0)
                <p class="empty-note">Belum ada kas keluar hari ini.</p>
            @else
                <table class="data-table">
                    <thead><tr><th>Kategori</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach ($keluarCategories as $row)
                            <tr><td>{{ $row['category_name'] }}</td><td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="panel">
        <h3>Kas Masuk &amp; Keluar vs Tren Saldo — 14 Hari Terakhir</h3>
        @php
            $balTrend = $summary['daily_balance_trend'];
            $balValues = array_column($balTrend, 'balance');
            $balMax = max([1, ...$balValues]);
            $balMin = min([0, ...$balValues]);
            $balRange = max(1, $balMax - $balMin);
            $flowMax = max([1, ...array_column($balTrend, 'masuk'), ...array_column($balTrend, 'keluar')]);
            $groupW = 34; $miniBarW = 15; $barGap = 10; $balChartH = 140;
            $balPoints = [];
            $balBars = [];
            foreach ($balTrend as $i => $day) {
                $x = $i * ($groupW + $barGap);
                $hMasuk = $day['masuk'] > 0 ? max(2, ($day['masuk'] / $flowMax) * $balChartH) : 0;
                $hKeluar = $day['keluar'] > 0 ? max(2, ($day['keluar'] / $flowMax) * $balChartH) : 0;
                $lineY = $balChartH - max(0, (($day['balance'] - $balMin) / $balRange) * $balChartH);
                $fullDate = \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('d M Y');
                $lineTooltip = \App\Support\DashboardChart::tooltip($fullDate, [
                    ['label' => 'Kas Masuk', 'value' => \App\Support\DashboardChart::money($day['masuk']), 'color' => 'var(--ok)'],
                    ['label' => 'Kas Keluar', 'value' => \App\Support\DashboardChart::money($day['keluar']), 'color' => 'var(--brick)'],
                    ['label' => 'Saldo', 'value' => \App\Support\DashboardChart::money($day['balance']), 'color' => 'var(--pine)', 'strong' => true],
                ]);
                $balBars[] = [
                    'x' => $x, 'label' => $day['label'],
                    'hMasuk' => $hMasuk, 'hKeluar' => $hKeluar, 'lineY' => $lineY,
                    'masukTooltip' => \App\Support\DashboardChart::tooltip($fullDate, [['label' => 'Kas Masuk', 'value' => \App\Support\DashboardChart::money($day['masuk']), 'color' => 'var(--ok)', 'strong' => true]]),
                    'keluarTooltip' => \App\Support\DashboardChart::tooltip($fullDate, [['label' => 'Kas Keluar', 'value' => \App\Support\DashboardChart::money($day['keluar']), 'color' => 'var(--brick)', 'strong' => true]]),
                    'lineTooltip' => $lineTooltip,
                ];
                $balPoints[] = [$x + $groupW / 2, $lineY];
            }
            $masukTotal = array_sum(array_column($balTrend, 'masuk'));
            $keluarTotal = array_sum(array_column($balTrend, 'keluar'));
            $balTotal = end($balValues);
            $masukLegendTooltip = \App\Support\DashboardChart::tooltip('Total 14 Hari Terakhir', [['label' => 'Kas Masuk', 'value' => \App\Support\DashboardChart::money($masukTotal), 'color' => 'var(--ok)', 'strong' => true]]);
            $keluarLegendTooltip = \App\Support\DashboardChart::tooltip('Total 14 Hari Terakhir', [['label' => 'Kas Keluar', 'value' => \App\Support\DashboardChart::money($keluarTotal), 'color' => 'var(--brick)', 'strong' => true]]);
            $lineLegendTooltip = \App\Support\DashboardChart::tooltip('Saldo Saat Ini', [['label' => 'Saldo Kas & Bank', 'value' => \App\Support\DashboardChart::money($balTotal), 'color' => 'var(--pine)', 'strong' => true]]);
        @endphp
        <div class="chart-wrap">
            <div class="chart-legend">
                <span class="chart-legend-item" data-series="masuk" tabindex="0" data-tooltip="{{ $masukLegendTooltip }}">
                    <span class="chart-legend-dot" style="background: var(--ok);"></span> Kas Masuk
                </span>
                <span class="chart-legend-item" data-series="keluar" tabindex="0" data-tooltip="{{ $keluarLegendTooltip }}">
                    <span class="chart-legend-dot" style="background: var(--brick);"></span> Kas Keluar
                </span>
                <span class="chart-legend-item" data-series="saldo" tabindex="0" data-tooltip="{{ $lineLegendTooltip }}">
                    <span class="chart-legend-line" style="background: var(--pine);"></span> Saldo (garis)
                </span>
            </div>
            <svg viewBox="0 0 {{ count($balTrend) * ($groupW + $barGap) }} {{ $balChartH + 30 }}" style="width:100%; height:200px;" role="img" aria-label="Grafik kas masuk, kas keluar, dan tren saldo kas/bank 14 hari terakhir">
                @foreach ($balBars as $bar)
                    <rect class="act-mark" data-series="masuk" tabindex="0" data-tooltip="{{ $bar['masukTooltip'] }}"
                        x="{{ $bar['x'] }}" y="{{ $balChartH - $bar['hMasuk'] }}" width="{{ $miniBarW }}" height="{{ $bar['hMasuk'] }}" rx="2" fill="var(--ok)"></rect>
                    <rect class="act-mark" data-series="keluar" tabindex="0" data-tooltip="{{ $bar['keluarTooltip'] }}"
                        x="{{ $bar['x'] + $miniBarW + 4 }}" y="{{ $balChartH - $bar['hKeluar'] }}" width="{{ $miniBarW }}" height="{{ $bar['hKeluar'] }}" rx="2" fill="var(--brick)"></rect>
                    <text x="{{ $bar['x'] + $groupW / 2 }}" y="{{ $balChartH + 16 }}" text-anchor="middle" class="trend-bar-label">{{ $bar['label'] }}</text>
                @endforeach
                <path d="{{ \App\Support\DashboardChart::smoothPath($balPoints) }}" fill="none" stroke="var(--pine)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                @foreach ($balBars as $bar)
                    <circle class="act-mark" data-series="saldo" tabindex="0" data-tooltip="{{ $bar['lineTooltip'] }}"
                        cx="{{ $bar['x'] + $groupW / 2 }}" cy="{{ $bar['lineY'] }}" r="3.5" fill="var(--pine)"></circle>
                @endforeach
            </svg>
            <div class="chart-tooltip" role="status" aria-live="polite"></div>
        </div>
    </div>

    <div class="panel">
        <h3>Saldo per Akun Kas/Bank</h3>
        @php
            $balanceRows = $summary['balances'];
            $balanceMax = max([1, ...$balanceRows->pluck('balance')->all()]);
            $chartPalette = ['var(--pine)', 'var(--gold)', '#5C6E64', 'var(--brick)', '#3C6E91', '#8A5FA8'];
        @endphp
        @foreach ($balanceRows as $i => $row)
            <div class="breakdown-row">
                <div class="breakdown-label">
                    <span>{{ $row['account']->code }} — {{ $row['account']->name }}</span>
                    <span>Rp {{ number_format($row['balance'], 0, ',', '.') }}</span>
                </div>
                <div class="breakdown-track">
                    <div class="breakdown-fill" style="width: {{ $row['balance'] > 0 ? ($row['balance'] / $balanceMax) * 100 : 0 }}%; background: {{ $chartPalette[$i % count($chartPalette)] }};"></div>
                </div>
            </div>
        @endforeach
        <table class="data-table">
            <thead><tr><th>Akun</th><th>Saldo</th></tr></thead>
            <tbody>
                @foreach ($balanceRows as $row)
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
            @php
                $categoryMax = max([1, ...array_column($summary['category_breakdown'], 'total')]);
            @endphp
            @foreach ($summary['category_breakdown'] as $row)
                <div class="breakdown-row">
                    <div class="breakdown-label">
                        <span>{{ $row['category_name'] }} <span class="type-tag {{ $row['type'] }}">{{ ucfirst($row['type']) }}</span></span>
                        <span>Rp {{ number_format($row['total'], 0, ',', '.') }}</span>
                    </div>
                    <div class="breakdown-track">
                        <div class="breakdown-fill" style="width: {{ ($row['total'] / $categoryMax) * 100 }}%; background: {{ $row['type'] === 'masuk' ? 'var(--ok)' : 'var(--brick)' }};"></div>
                    </div>
                </div>
            @endforeach
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
