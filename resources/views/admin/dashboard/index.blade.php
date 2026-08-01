@extends('layouts.app')

@section('title', 'Dashboard Utama')

@section('content')
    <style>
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .branch-picker select { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
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
        @media (max-width: 980px) { .kpi-grid { grid-template-columns: 1fr 1fr; } }

        .breakdown-row { margin-bottom: 10px; }
        .breakdown-row .breakdown-label { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
        .breakdown-track { height: 10px; background: var(--paper); border-radius: 6px; overflow: hidden; }
        .breakdown-fill { height: 100%; border-radius: 6px; }

        .problem-list h4 { margin: 0 0 10px; font-size: 13px; }
        .problem-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--line); }
        .problem-row:last-child { border-bottom: none; }
        .problem-name { font-size: 13px; font-weight: 700; }
        .problem-sub { font-size: 11px; color: var(--muted); }
        .problem-amount { font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; }
        .collect-tag { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 10px; margin-top: 3px; }
        .collect-tag.kurang_lancar { background: var(--gold-soft); color: var(--gold-deep); }
        .collect-tag.diragukan, .collect-tag.macet { background: var(--brick-soft); color: var(--brick); }
        .status-tag { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 10px; margin-top: 3px; }
        .status-tag.pending { background: var(--gold-soft); color: var(--gold-deep); }
        .status-tag.success { background: var(--leaf); color: var(--pine-bright); }
        .status-tag.danger { background: var(--brick-soft); color: var(--brick); }

        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 980px) { .chart-grid { grid-template-columns: 1fr; } }
        .chart-grid .panel { margin-bottom: 0; }

        .trend-bar-label { font-size: 10px; fill: var(--muted); }
        .chart-legend { display: flex; gap: 16px; margin-bottom: 10px; font-size: 12px; flex-wrap: wrap; }
        .chart-legend-item { display: flex; align-items: center; gap: 6px; }
        .chart-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .chart-legend-line { width: 14px; height: 2px; border-radius: 2px; flex-shrink: 0; }
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
        <div class="kpi-card tone-pine" data-detail-target="kpi-detail-anggota" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-anggota">
            <span class="kpi-icon">@include('partials.icon', ['name' => 'users', 'size' => 20])</span>
            <div class="label">Total Anggota</div>
            <div class="val">{{ number_format($summary['total_members'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card tone-gold" data-detail-target="kpi-detail-simpanan" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-simpanan">
            <span class="kpi-icon">@include('partials.icon', ['name' => 'piggy-bank', 'size' => 20])</span>
            <div class="label">Total Simpanan</div>
            <div class="val">Rp {{ number_format($summary['total_savings'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card tone-mix" data-detail-target="kpi-detail-pinjaman" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-pinjaman">
            <span class="kpi-icon">@include('partials.icon', ['name' => 'credit-card', 'size' => 20])</span>
            <div class="label">Pinjaman Outstanding</div>
            <div class="val">Rp {{ number_format($summary['total_loan_outstanding'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card feature" data-detail-target="kpi-detail-shu" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-shu">
            <span class="kpi-icon">@include('partials.icon', ['name' => 'trending-up', 'size' => 20])</span>
            <div class="label">SHU Berjalan</div>
            <div class="val">Rp {{ number_format($summary['shu_running'], 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="kpi-detail-panels">
        <div class="kpi-detail" id="kpi-detail-anggota" hidden>
            <h4>Rincian Total Anggota per Jenis</h4>
            @if ($summary['members_by_type']->isEmpty())
                <p class="empty-note">Belum ada anggota.</p>
            @else
                <table class="data-table">
                    <thead><tr><th>Jenis Anggota</th><th>Jumlah</th></tr></thead>
                    <tbody>
                        @foreach ($summary['members_by_type'] as $row)
                            <tr><td>{{ $row->type_name }}</td><td>{{ number_format($row->total, 0, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        <div class="kpi-detail" id="kpi-detail-simpanan" hidden>
            <h4>Rincian Total Simpanan per Produk</h4>
            @if ($summary['savings_by_product']->isEmpty())
                <p class="empty-note">Belum ada transaksi simpanan.</p>
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
        <div class="kpi-detail" id="kpi-detail-pinjaman" hidden>
            <h4>Rincian Pinjaman Outstanding per Kolektibilitas</h4>
            @php
                $collectLabels = ['lancar' => 'Lancar', 'kurang_lancar' => 'Kurang Lancar', 'diragukan' => 'Diragukan', 'macet' => 'Macet'];
            @endphp
            @if ($summary['loan_outstanding_by_collectibility']->isEmpty())
                <p class="empty-note">Belum ada pinjaman berjalan.</p>
            @else
                <table class="data-table">
                    <thead><tr><th>Kolektibilitas</th><th>Jumlah Pinjaman</th><th>Total Outstanding</th></tr></thead>
                    <tbody>
                        @foreach ($summary['loan_outstanding_by_collectibility'] as $row)
                            <tr>
                                <td>{{ $collectLabels[$row->collectibility] ?? ucfirst($row->collectibility) }}</td>
                                <td>{{ number_format($row->count, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($row->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        <div class="kpi-detail" id="kpi-detail-shu" hidden>
            <h4>Rincian SHU Berjalan</h4>
            <table class="data-table">
                <tbody>
                    <tr><td>Pendapatan</td><td>Rp {{ number_format($summary['shu_breakdown']['pendapatan'], 0, ',', '.') }}</td></tr>
                    <tr><td>Beban</td><td>Rp {{ number_format($summary['shu_breakdown']['beban'], 0, ',', '.') }}</td></tr>
                    <tr><td><strong>SHU Berjalan</strong></td><td><strong>Rp {{ number_format($summary['shu_breakdown']['shu'], 0, ',', '.') }}</strong></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h3>Aktivitas Harian — Simpanan, Pinjaman &amp; Angsuran (14 Hari Terakhir)</h3>
        @php
            $activity = $summary['daily_activity'];
            $actMax = max([1, ...array_column($activity, 'total')]);
            $barW = 9; $barGapIn = 2; $groupGap = 12;
            $groupW = ($barW * 3) + ($barGapIn * 2);
            $dayStep = $groupW + $groupGap;
            $actChartH = 140;
            $actLinePoints = [];

            $seriesTotal = fn (string $key) => array_sum(array_column($activity, $key));
            $singleTooltip = fn (string $date, string $label, string $color, $value) => \App\Support\DashboardChart::tooltip(
                $date,
                [['label' => $label, 'value' => \App\Support\DashboardChart::money($value), 'color' => $color, 'strong' => true]],
            );
        @endphp
        <div id="activity-chart" class="chart-wrap">
            <div class="chart-legend">
                <span class="chart-legend-item" data-series="simpanan" tabindex="0"
                    data-tooltip="{{ $singleTooltip('Total 14 Hari Terakhir', 'Simpanan', 'var(--pine)', $seriesTotal('simpanan')) }}">
                    <span class="chart-legend-dot" style="background: var(--pine);"></span> Simpanan
                </span>
                <span class="chart-legend-item" data-series="pinjaman" tabindex="0"
                    data-tooltip="{{ $singleTooltip('Total 14 Hari Terakhir', 'Pinjaman Dicairkan', 'var(--gold)', $seriesTotal('pinjaman')) }}">
                    <span class="chart-legend-dot" style="background: var(--gold);"></span> Pinjaman Dicairkan
                </span>
                <span class="chart-legend-item" data-series="angsuran" tabindex="0"
                    data-tooltip="{{ $singleTooltip('Total 14 Hari Terakhir', 'Angsuran Jatuh Tempo', '#5C6E64', $seriesTotal('angsuran')) }}">
                    <span class="chart-legend-dot" style="background: #5C6E64;"></span> Angsuran Jatuh Tempo
                </span>
                <span class="chart-legend-item" data-series="total" tabindex="0"
                    data-tooltip="{{ $singleTooltip('Total 14 Hari Terakhir', 'Total Harian', 'var(--brick)', $seriesTotal('total')) }}">
                    <span class="chart-legend-line" style="background: var(--brick);"></span> Total Harian (garis)
                </span>
            </div>
            <svg viewBox="0 0 {{ count($activity) * $dayStep }} {{ $actChartH + 30 }}" style="width:100%; height:220px;" role="img" aria-label="Grafik aktivitas harian simpanan, pinjaman, dan angsuran 14 hari terakhir">
                @foreach ($activity as $i => $day)
                    @php
                        $groupX = $i * $dayStep;
                        $simpananH = max(0, ($day['simpanan'] / $actMax) * $actChartH);
                        $pinjamanH = max(0, ($day['pinjaman'] / $actMax) * $actChartH);
                        $angsuranH = max(0, ($day['angsuran'] / $actMax) * $actChartH);
                        $totalY = $actChartH - max(0, ($day['total'] / $actMax) * $actChartH);
                        $actLinePoints[] = [$groupX + $groupW / 2, $totalY];
                        $fullDate = \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('d M Y');
                    @endphp
                    <rect class="act-mark" data-series="simpanan" tabindex="0"
                        data-tooltip="{{ $singleTooltip($fullDate, 'Simpanan', 'var(--pine)', $day['simpanan']) }}"
                        x="{{ $groupX }}" y="{{ $actChartH - $simpananH }}" width="{{ $barW }}" height="{{ $simpananH }}" rx="2" fill="var(--pine)"></rect>
                    <rect class="act-mark" data-series="pinjaman" tabindex="0"
                        data-tooltip="{{ $singleTooltip($fullDate, 'Pinjaman Dicairkan', 'var(--gold)', $day['pinjaman']) }}"
                        x="{{ $groupX + $barW + $barGapIn }}" y="{{ $actChartH - $pinjamanH }}" width="{{ $barW }}" height="{{ $pinjamanH }}" rx="2" fill="var(--gold)"></rect>
                    <rect class="act-mark" data-series="angsuran" tabindex="0"
                        data-tooltip="{{ $singleTooltip($fullDate, 'Angsuran Jatuh Tempo', '#5C6E64', $day['angsuran']) }}"
                        x="{{ $groupX + 2 * ($barW + $barGapIn) }}" y="{{ $actChartH - $angsuranH }}" width="{{ $barW }}" height="{{ $angsuranH }}" rx="2" fill="#5C6E64"></rect>
                    <text x="{{ $groupX + $groupW / 2 }}" y="{{ $actChartH + 16 }}" text-anchor="middle" class="trend-bar-label">{{ $day['label'] }}</text>
                @endforeach
                <path d="{{ \App\Support\DashboardChart::smoothPath($actLinePoints) }}" fill="none" stroke="var(--brick)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                @foreach ($activity as $i => $day)
                    @php
                        [$px, $py] = $actLinePoints[$i];
                        $fullDate = \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('d M Y');
                        $pointTooltip = \App\Support\DashboardChart::tooltip($fullDate, [
                            ['label' => 'Simpanan', 'value' => \App\Support\DashboardChart::money($day['simpanan']), 'color' => 'var(--pine)'],
                            ['label' => 'Pinjaman Dicairkan', 'value' => \App\Support\DashboardChart::money($day['pinjaman']), 'color' => 'var(--gold)'],
                            ['label' => 'Angsuran Jatuh Tempo', 'value' => \App\Support\DashboardChart::money($day['angsuran']), 'color' => '#5C6E64'],
                            ['label' => 'Total Harian', 'value' => \App\Support\DashboardChart::money($day['total']), 'color' => 'var(--brick)', 'strong' => true],
                        ]);
                    @endphp
                    <circle class="act-mark" data-series="total" tabindex="0"
                        data-tooltip="{{ $pointTooltip }}"
                        cx="{{ $px }}" cy="{{ $py }}" r="3.5" fill="var(--brick)"></circle>
                @endforeach
            </svg>
            <div class="chart-tooltip" role="status" aria-live="polite"></div>
        </div>
    </div>

    <div class="chart-grid" style="margin-bottom: 20px;">
    <div class="panel">
        <h3>Simpanan per Produk</h3>
        @if ($summary['savings_by_product']->isEmpty())
            <p class="empty-note">Belum ada transaksi simpanan pada cabang/periode ini.</p>
        @else
            @php
                $savingsRows = $summary['savings_by_product'];
                $savingsMax = max([1, ...$savingsRows->pluck('total')->all()]);
                $chartPalette ??= ['var(--pine)', 'var(--gold)', '#5C6E64', 'var(--brick)', '#3C6E91', '#8A5FA8'];
            @endphp
            @foreach ($savingsRows as $i => $row)
                <div class="breakdown-row">
                    <div class="breakdown-label">
                        <span>{{ $row->product_name }}</span>
                        <span>Rp {{ number_format($row->total, 0, ',', '.') }}</span>
                    </div>
                    <div class="breakdown-track">
                        <div class="breakdown-fill" style="width: {{ ($row->total / $savingsMax) * 100 }}%; background: {{ $chartPalette[$i % count($chartPalette)] }};"></div>
                    </div>
                </div>
            @endforeach
            <table class="data-table">
                <thead><tr><th>Produk</th><th>Total Saldo</th></tr></thead>
                <tbody>
                    @foreach ($savingsRows as $row)
                        <tr><td>{{ $row->product_name }}</td><td>Rp {{ number_format($row->total, 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="panel">
        <h3>Anggota Bermasalah (Pinjaman)</h3>
        @php
            $collectLabels = ['kurang_lancar' => 'Kurang Lancar', 'diragukan' => 'Diragukan', 'macet' => 'Macet'];
        @endphp
        @forelse ($summary['problematic_loans'] as $loan)
            <div class="problem-row">
                <div>
                    <div class="problem-name">{{ $loan->member->name }}</div>
                    <div class="problem-sub">{{ $loan->loan_number }}</div>
                </div>
                <div>
                    <div class="problem-amount">Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</div>
                    <span class="collect-tag {{ $loan->collectibility }}">{{ $collectLabels[$loan->collectibility] ?? ucfirst($loan->collectibility) }}</span>
                </div>
            </div>
        @empty
            <p class="empty-note">Tidak ada pinjaman bermasalah saat ini.</p>
        @endforelse
    </div>
    </div>

    <div class="chart-grid" style="margin-bottom: 20px;">
    <div class="panel problem-list">
        <h3>Aktivitas Pinjaman Terbaru</h3>
        @php
            $loanStatusLabels = ['diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'dicairkan' => 'Dicairkan', 'lunas' => 'Lunas', 'dibatalkan' => 'Dibatalkan'];
            $loanStatusTone = ['diajukan' => 'pending', 'disetujui' => 'pending', 'ditolak' => 'danger', 'dicairkan' => 'success', 'lunas' => 'success', 'dibatalkan' => 'danger'];
        @endphp
        @forelse ($summary['recent_loan_activity'] as $loan)
            <div class="problem-row">
                <div>
                    <div class="problem-name">{{ $loan->member->name }}</div>
                    <div class="problem-sub">{{ $loan->loan_number }} — {{ $loan->updated_at->diffForHumans() }}</div>
                </div>
                <div>
                    <div class="problem-amount">Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</div>
                    <span class="status-tag {{ $loanStatusTone[$loan->status] ?? 'pending' }}">{{ $loanStatusLabels[$loan->status] ?? ucfirst($loan->status) }}</span>
                </div>
            </div>
        @empty
            <p class="empty-note">Belum ada aktivitas pinjaman pada cabang/periode ini.</p>
        @endforelse
    </div>

    <div class="panel problem-list">
        <h3>Daftar Angsuran Jatuh Tempo</h3>
        @forelse ($summary['due_installments'] as $schedule)
            @php $isOverdue = $schedule->due_date->isPast(); @endphp
            <div class="problem-row">
                <div>
                    <div class="problem-name">{{ $schedule->loan->member->name }}</div>
                    <div class="problem-sub">{{ $schedule->loan->loan_number }} — Angsuran ke-{{ $schedule->installment_number }}</div>
                </div>
                <div>
                    <div class="problem-amount">Rp {{ number_format($schedule->total_amount, 0, ',', '.') }}</div>
                    <span class="collect-tag {{ $isOverdue ? 'macet' : 'kurang_lancar' }}">{{ $isOverdue ? 'Terlambat — ' : '' }}{{ $schedule->due_date->translatedFormat('d M Y') }}</span>
                </div>
            </div>
        @empty
            <p class="empty-note">Tidak ada angsuran jatuh tempo saat ini.</p>
        @endforelse
    </div>
    </div>

@endsection
