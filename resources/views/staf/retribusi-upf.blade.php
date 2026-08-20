@extends('layouts.app')

@section('title', 'Retribusi (UPF)')

@section('content')
    <style>
        .tab-nav { display: flex; gap: 6px; margin-bottom: 18px; border-bottom: 1px solid var(--line); }
        .tab-btn { padding: 10px 16px; background: none; border: none; border-bottom: 2px solid transparent; font-weight: 700; font-size: 13px; color: var(--muted); cursor: pointer; }
        .tab-btn.active { color: var(--pine); border-bottom-color: var(--pine); }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }

        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
        .kpi-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
        .kpi-card .kpi-label { font-size: 12px; color: var(--muted); font-weight: 600; }
        .kpi-card .kpi-value { font-size: 22px; font-weight: 800; color: var(--pine-ink); margin-top: 6px; }
        .kpi-card .kpi-sub { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .kpi-card.tone-pine { background: linear-gradient(160deg, var(--pine), var(--pine-deep)); border: none; }
        .kpi-card.tone-gold { background: linear-gradient(160deg, var(--gold-deep), var(--gold)); border: none; }
        .kpi-card.tone-mix { background: linear-gradient(160deg, var(--pine-deep), var(--gold-deep)); border: none; }
        .kpi-card.tone-pine .kpi-label, .kpi-card.tone-gold .kpi-label, .kpi-card.tone-mix .kpi-label { color: rgba(255,255,255,.75); }
        .kpi-card.tone-pine .kpi-value, .kpi-card.tone-gold .kpi-value, .kpi-card.tone-mix .kpi-value { color: #fff; }
        .kpi-card.tone-pine .kpi-sub, .kpi-card.tone-gold .kpi-sub, .kpi-card.tone-mix .kpi-sub { color: rgba(255,255,255,.65); }
        @media (max-width: 980px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }

        .donut-row { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
        .donut-legend { flex: 1; min-width: 150px; }
        .donut-legend-item { display: flex; align-items: center; gap: 8px; font-size: 12px; margin-bottom: 7px; }
        .donut-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .donut-legend-item .amt { margin-left: auto; color: var(--muted); font-variant-numeric: tabular-nums; }

        .chart-grid { display: grid; grid-template-columns: 1.3fr 1fr; gap: 16px; margin-bottom: 22px; }
        @media (max-width: 980px) { .chart-grid { grid-template-columns: 1fr; } }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; }
        .panel h3 { margin-top: 0; font-size: 14px; }
        .trend-bar-label { font-size: 10px; fill: var(--muted); }
        .trend-bar-value { font-size: 10px; fill: var(--pine-ink); font-weight: 700; }

        .breakdown-row { margin-bottom: 10px; }
        .breakdown-row .breakdown-label { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
        .breakdown-track { height: 10px; background: var(--paper); border-radius: 6px; overflow: hidden; }
        .breakdown-fill { height: 100%; border-radius: 6px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 980px) { .grid-2 { grid-template-columns: 1fr; } }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field-row { display: flex; gap: 14px; }
        .field-row .field { flex: 1; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .payer-toggle { display: flex; gap: 8px; margin-bottom: 14px; }
        .payer-toggle label { flex: 1; text-align: center; padding: 9px; border: 1px solid var(--line); border-radius: 9px; cursor: pointer; font-size: 13px; font-weight: 600; color: var(--muted); }
        .payer-toggle input { display: none; }
        .payer-toggle input:checked + span { color: #fff; }
        .payer-toggle label:has(input:checked) { background: var(--pine); border-color: var(--pine); color: #fff; }

        .preview-line { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-bottom: 1px solid var(--line); }
        .preview-line.total { font-weight: 800; border-bottom: none; padding-top: 10px; }
        .preview-empty { color: var(--muted); font-size: 12px; }

        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-top: 18px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }

        .checkbox-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 14px; }
        .checkbox-grid label { font-size: 12px; display: flex; align-items: center; gap: 6px; }
        .checkbox-grid input { width: auto; }
    </style>

    <h2>Retribusi (UPF)</h2>
    <p style="color: var(--muted); margin-top: -8px;">Pencatatan penerimaan retribusi harian dengan pembagian otomatis.</p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <nav class="tab-nav">
        <button type="button" class="tab-btn" data-tab="dashboard">📊 Dashboard</button>
        <button type="button" class="tab-btn" data-tab="transaksi">💳 Transaksi</button>
        <button type="button" class="tab-btn" data-tab="laporan">📄 Laporan</button>
    </nav>

    {{-- ================= DASHBOARD TAB ================= --}}
    <div class="tab-panel" data-tab-panel="dashboard">
        <div class="kpi-grid">
            <div class="kpi-card tone-mix" data-detail-target="kpi-detail-hari-ini" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-hari-ini">
                <span class="kpi-icon">@include('partials.icon', ['name' => 'trending-up', 'size' => 20])</span>
                <div class="kpi-label">Pendapatan Hari Ini</div>
                <div class="kpi-value">Rp {{ number_format($summary['today_revenue'], 0, ',', '.') }}</div>
                <div class="kpi-sub">{{ $summary['today_transaction_count'] }} transaksi</div>
            </div>
            <div class="kpi-card tone-gold" data-detail-target="kpi-detail-bulan-ini" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-bulan-ini">
                <span class="kpi-icon">@include('partials.icon', ['name' => 'calendar', 'size' => 20])</span>
                <div class="kpi-label">Pendapatan Bulan Ini</div>
                <div class="kpi-value">Rp {{ number_format($summary['month_revenue'], 0, ',', '.') }}</div>
            </div>
            <div class="kpi-card tone-pine" data-detail-target="kpi-detail-seluruhnya" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-seluruhnya">
                <span class="kpi-icon">@include('partials.icon', ['name' => 'archive', 'size' => 20])</span>
                <div class="kpi-label">Total Seluruhnya</div>
                <div class="kpi-value">Rp {{ number_format($summary['total_revenue'], 0, ',', '.') }}</div>
            </div>
            <div class="kpi-card tone-pine" data-detail-target="kpi-detail-pembayar" role="button" tabindex="0" aria-expanded="false" aria-controls="kpi-detail-pembayar">
                <span class="kpi-icon">@include('partials.icon', ['name' => 'users', 'size' => 20])</span>
                <div class="kpi-label">Pembayar Hari Ini</div>
                <div class="kpi-value">{{ $summary['today_payer_count'] }}</div>
            </div>
        </div>

        <div class="kpi-detail-panels">
            <div class="kpi-detail" id="kpi-detail-hari-ini" hidden>
                <h4>Rincian Pendapatan Hari Ini per Jenis Retribusi</h4>
                @if (count($summary['breakdown_by_type_today']) === 0)
                    <p class="preview-empty">Belum ada retribusi hari ini.</p>
                @else
                    <table class="data-table">
                        <thead><tr><th>Jenis</th><th>Total</th></tr></thead>
                        <tbody>
                            @foreach ($summary['breakdown_by_type_today'] as $row)
                                <tr><td>{{ $row['name'] }}</td><td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="kpi-detail" id="kpi-detail-bulan-ini" hidden>
                <h4>Rincian Pendapatan Bulan Ini per Jenis Retribusi</h4>
                @if (count($summary['breakdown_by_type_month']) === 0)
                    <p class="preview-empty">Belum ada retribusi bulan ini.</p>
                @else
                    <table class="data-table">
                        <thead><tr><th>Jenis</th><th>Total</th></tr></thead>
                        <tbody>
                            @foreach ($summary['breakdown_by_type_month'] as $row)
                                <tr><td>{{ $row['name'] }}</td><td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="kpi-detail" id="kpi-detail-seluruhnya" hidden>
                <h4>Rincian Total Seluruhnya per Jenis Retribusi</h4>
                @if (count($summary['breakdown_by_type']) === 0)
                    <p class="preview-empty">Belum ada data.</p>
                @else
                    <table class="data-table">
                        <thead><tr><th>Jenis</th><th>Total</th></tr></thead>
                        <tbody>
                            @foreach ($summary['breakdown_by_type'] as $row)
                                <tr><td>{{ $row['name'] }}</td><td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="kpi-detail" id="kpi-detail-pembayar" hidden>
                <h4>Rincian Pembayar Hari Ini</h4>
                @if (count($summary['today_payers']) === 0)
                    <p class="preview-empty">Belum ada pembayar hari ini.</p>
                @else
                    <table class="data-table">
                        <thead><tr><th>Pembayar</th><th>Total Bayar</th></tr></thead>
                        <tbody>
                            @foreach ($summary['today_payers'] as $row)
                                <tr><td>{{ $row['name'] }}</td><td>Rp {{ number_format($row['total'], 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="chart-grid">
            <div class="panel">
                <h3>Tren 7 Hari Terakhir</h3>
                @php
                    $trend = $summary['seven_day_trend'];
                    $max = max([1, ...array_column($trend, 'total')]);
                    $barWidth = 60; $gap = 20; $chartHeight = 140;
                @endphp
                <div class="chart-wrap">
                    <svg viewBox="0 0 {{ count($trend) * ($barWidth + $gap) }} {{ $chartHeight + 30 }}" style="width:100%; height:180px;" role="img" aria-label="Grafik tren pendapatan retribusi 7 hari terakhir">
                        @foreach ($trend as $i => $day)
                            @php
                                $height = $day['total'] > 0 ? max(4, ($day['total'] / $max) * $chartHeight) : 2;
                                $x = $i * ($barWidth + $gap);
                                $y = $chartHeight - $height;
                            @endphp
                            <rect class="act-mark" data-series="pendapatan" tabindex="0"
                                data-tooltip="{{ \App\Support\DashboardChart::tooltip($day['label'], [['label' => 'Pendapatan Retribusi', 'value' => \App\Support\DashboardChart::money($day['total']), 'color' => 'var(--pine)', 'strong' => true]]) }}"
                                x="{{ $x }}" y="{{ $y }}" width="{{ $barWidth }}" height="{{ $height }}" rx="4" fill="var(--pine)"></rect>
                            <text x="{{ $x + $barWidth / 2 }}" y="{{ $chartHeight + 16 }}" text-anchor="middle" class="trend-bar-label">{{ $day['label'] }}</text>
                        @endforeach
                    </svg>
                    <div class="chart-tooltip" role="status" aria-live="polite"></div>
                </div>
            </div>

            <div class="panel">
                <h3>Pendapatan per Jenis Retribusi</h3>
                @php
                    $breakdown = $summary['breakdown_by_type'];
                    $breakdownMax = max([1, ...array_column($breakdown, 'total')]);
                    $palette = ['var(--pine)', 'var(--gold)', '#5C6E64', 'var(--brick)', '#3C6E91', '#8A5FA8'];
                @endphp
                @forelse ($breakdown as $i => $row)
                    <div class="breakdown-row">
                        <div class="breakdown-label">
                            <span>{{ $row['name'] }}</span>
                            <span>Rp {{ number_format($row['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="breakdown-track">
                            <div class="breakdown-fill" style="width: {{ ($row['total'] / $breakdownMax) * 100 }}%; background: {{ $palette[$i % count($palette)] }};"></div>
                        </div>
                    </div>
                @empty
                    <p class="preview-empty">Belum ada data.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <h3>Proporsi Pendapatan per Jenis Retribusi</h3>
            @if (count($breakdown) === 0)
                <p class="preview-empty">Belum ada data.</p>
            @else
                @php
                    $donutTotal = array_sum(array_column($breakdown, 'total'));
                    $donutRadius = 60; $donutCircumference = 2 * M_PI * $donutRadius;
                    $donutCumulative = 0;
                    $donutTooltip = fn (array $row, int $i) => \App\Support\DashboardChart::tooltip($row['name'], [
                        ['label' => 'Pendapatan', 'value' => \App\Support\DashboardChart::money($row['total']), 'color' => $palette[$i % count($palette)]],
                        ['label' => 'Proporsi', 'value' => ($donutTotal > 0 ? number_format($row['total'] / $donutTotal * 100, 1) : 0).'%', 'color' => $palette[$i % count($palette)], 'strong' => true],
                    ]);
                @endphp
                <div class="donut-row chart-wrap">
                    <svg viewBox="0 0 160 160" style="width:160px; height:160px; flex-shrink:0;" role="img" aria-label="Grafik proporsi pendapatan retribusi per jenis">
                        <g transform="rotate(-90 80 80)">
                            @foreach ($breakdown as $i => $row)
                                @php
                                    $fraction = $donutTotal > 0 ? $row['total'] / $donutTotal : 0;
                                    $dash = $fraction * $donutCircumference;
                                    $offset = -$donutCumulative;
                                    $donutCumulative += $dash;
                                @endphp
                                <circle class="act-mark" data-series="{{ $i }}" tabindex="0" data-tooltip="{{ $donutTooltip($row, $i) }}"
                                        cx="80" cy="80" r="{{ $donutRadius }}" fill="none" stroke="{{ $palette[$i % count($palette)] }}"
                                        stroke-width="26" stroke-dasharray="{{ $dash }} {{ $donutCircumference - $dash }}" stroke-dashoffset="{{ $offset }}"></circle>
                            @endforeach
                        </g>
                    </svg>
                    <div class="donut-legend">
                        @foreach ($breakdown as $i => $row)
                            <div class="donut-legend-item chart-legend-item" data-series="{{ $i }}" tabindex="0" data-tooltip="{{ $donutTooltip($row, $i) }}">
                                <span class="donut-legend-dot" style="background: {{ $palette[$i % count($palette)] }};"></span>
                                <span>{{ $row['name'] }}</span>
                                <span class="amt">{{ $donutTotal > 0 ? number_format($row['total'] / $donutTotal * 100, 1) : 0 }}%</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="chart-tooltip" role="status" aria-live="polite"></div>
                </div>
            @endif
        </div>
    </div>

    {{-- ================= TRANSAKSI TAB ================= --}}
    <div class="tab-panel" data-tab-panel="transaksi">
        <div class="grid-2">
            <div class="panel">
                <h3>Catat Retribusi Harian</h3>

                @if ($errors->any())
                    <div class="error-msg">
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('staf.retribusi-upf.store') }}" id="retribusi-form">
                    @csrf

                    <div class="payer-toggle">
                        <label><input type="radio" name="payer_type" value="umum" id="payer-umum" @checked(old('payer_type', 'umum') === 'umum')><span>Umum</span></label>
                        <label><input type="radio" name="payer_type" value="anggota" id="payer-anggota" @checked(old('payer_type') === 'anggota')><span>Anggota</span></label>
                    </div>

                    <div class="field" id="field-payer-name">
                        <label>Nama Pembayar</label>
                        <input type="text" name="payer_name" value="{{ old('payer_name') }}" placeholder="Nama pembayar umum">
                    </div>
                    <div class="field" id="field-retribution-type">
                        <label>Jenis Retribusi <span style="color:var(--brick);">*</span></label>
                        <select name="retribution_type_id" id="retribution-type-select">
                            <option value="">— Pilih Jenis Retribusi —</option>
                            @foreach ($umumTypes as $type)
                                <option value="{{ $type->id }}"
                                        data-code="{{ $type->revenueAccount?->code }}"
                                        data-name="{{ $type->name }}"
                                        @selected((string) old('retribution_type_id') === (string) $type->id)>
                                    {{ $type->code }} — {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        @if ($umumTypes->isEmpty())
                            <p class="error-msg" style="margin-top:6px;">⚠ Belum ada jenis retribusi umum (persentase = 100%) yang aktif. Tambahkan di menu <em>Pengaturan → Jenis Retribusi</em>.</p>
                        @endif
                    </div>
                    <div class="field" id="field-member" style="display:none;">
                        <label>Anggota (Kios/Blok) <span style="color:var(--brick);">*</span></label>
                        <select name="member_id" class="js-searchable" id="member-select">
                            <option value="">— Pilih Anggota —</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected((string) old('member_id') === (string) $member->id)>
                                    {{ $member->member_number }} — {{ $member->name }}@if ($member->memberType) ({{ $member->memberType->code }})@endif
                                </option>
                            @endforeach
                        </select>
                        @if ($members->isEmpty())
                            <p class="error-msg" style="margin-top:6px;">⚠ Belum ada data anggota yang bisa dipilih. Pastikan master data Anggota sudah terisi (khususnya jenis KIOS/BLOK).</p>
                        @endif
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label>Tanggal</label>
                            <input type="date" name="transaction_date" value="{{ now()->toDateString() }}">
                        </div>
                        <div class="field">
                            <label>Cabang</label>
                            <select name="branch_id" required>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected($selectedBranchId === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label>Metode Bayar</label>
                            <select name="payment_method">
                                <option value="tunai">Tunai</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Total Iuran (Rp)</label>
                            <input type="number" step="0.01" min="0.01" name="total_amount" id="total-amount-input" required>
                        </div>
                    </div>

                    <div class="field">
                        <label>Keterangan</label>
                        <input type="text" name="description">
                    </div>

                    <button type="submit" class="btn-primary">Simpan</button>
                </form>
            </div>

            <div class="panel">
                <h3>Preview Jurnal</h3>
                <p class="preview-empty" id="preview-empty-msg">Pilih mode transaksi &amp; isi total iuran untuk melihat pembagian jurnal.</p>
                <div id="preview-lines" style="display:none;"></div>
                @if ($splitPercentageTotal != 100 && $splitTypes->isNotEmpty())
                    <p class="error-msg" style="margin-top:12px;">⚠ Total persentase jenis retribusi <em>split</em> (untuk pembagian otomatis Anggota) baru {{ number_format($splitPercentageTotal, 2) }}% — transaksi Anggota belum bisa diproses sampai mencapai 100%.</p>
                @endif
                @if ($splitTypes->isEmpty())
                    <p class="error-msg" style="margin-top:12px;">⚠ Belum ada jenis retribusi <em>split</em> (persentase &lt; 100%) yang aktif — transaksi <strong>Anggota</strong> belum tersedia. Tambahkan di menu <em>Pengaturan → Jenis Retribusi</em>.</p>
                @endif
                <p class="preview-empty" style="margin-top:14px; font-size:11px;">
                    Jurnal LAWAN (debit) diarahkan ke akun kas <strong>1101600 — KAS AO RIDWAN (UPF)</strong>.
                </p>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr><th>No. Transaksi</th><th>Tanggal</th><th>Pembayar</th><th>Cabang</th><th>Total</th><th>Metode</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($recentTransactions as $trx)
                    <tr>
                        <td>{{ $trx->transaction_number }}</td>
                        <td>{{ $trx->transaction_date->format('d/m/Y') }}</td>
                        <td>{{ $trx->payer_name }}</td>
                        <td>{{ $trx->branch->name }}</td>
                        <td>Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</td>
                        <td>{{ ucfirst($trx->payment_method) }}</td>
                        <td>
                            @if ($trx->isCancelled())
                                <span style="color:var(--brick);">Dibatalkan</span>
                            @elseif ($trx->canBeCancelledBy(auth()->user()))
                                <button type="button" class="btn-cancel-toggle" data-toggle-cancel="retribusi-{{ $trx->id }}" style="background:transparent;border:none;color:var(--brick);font-size:11px;font-weight:600;cursor:pointer;text-decoration:underline;">Batalkan</button>
                                <form method="POST" action="{{ route('staf.retribusi-upf.cancel', $trx) }}" class="cancel-form" id="cancel-form-retribusi-{{ $trx->id }}" style="display:none; gap:6px; margin-top:6px;">
                                    @csrf
                                    <input type="text" name="reason" placeholder="Alasan" required style="width:110px; padding:5px 8px; border:1px solid var(--line); border-radius:6px; font-size:11px;">
                                    <button type="submit" style="padding:5px 10px; background:var(--brick); color:#fff; border:none; border-radius:6px; font-size:11px; cursor:pointer;">OK</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada transaksi retribusi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= LAPORAN TAB ================= --}}
    <div class="tab-panel" data-tab-panel="laporan">
        <div class="panel">
            <h3>Cetak Laporan Harian</h3>
            <form method="GET" action="{{ route('staf.retribusi-upf.print-harian') }}" target="_blank" class="field-row">
                <div class="field">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="field">
                    <label>Cabang</label>
                    <select name="branch_id">
                        <option value="">Semua Cabang</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-secondary">Cetak</button>
            </form>
        </div>

        <div class="panel">
            <h3>Rekap Pendapatan UPF (Portrait)</h3>
            <p style="color: var(--muted); font-size: 12px; margin-top: -4px; margin-bottom: 12px;">
                Cetak PDF portrait berisi total kas masuk &amp; rekap per jenis retribusi.
                Preset periode: <a href="#" data-preset-rekap="today" style="color:var(--pine);">Hari ini</a> ·
                <a href="#" data-preset-rekap="2days" style="color:var(--pine);">2 hari</a> ·
                <a href="#" data-preset-rekap="week" style="color:var(--pine);">1 minggu</a> ·
                <a href="#" data-preset-rekap="month" style="color:var(--pine);">1 bulan</a>.
            </p>
            <form method="GET" action="{{ route('staf.retribusi-upf.print-rekap') }}" target="_blank" id="rekap-form">
                <div class="field-row">
                    <div class="field">
                        <label>Periode Mulai</label>
                        <input type="date" name="period_start" id="rekap-start" value="{{ now()->startOfMonth()->toDateString() }}" required>
                    </div>
                    <div class="field">
                        <label>Periode Akhir</label>
                        <input type="date" name="period_end" id="rekap-end" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="field">
                        <label>Cabang</label>
                        <select name="branch_id">
                            <option value="">Semua Cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Cetak Rekap</button>
            </form>
        </div>

        <div class="panel">
            <h3>Konfigurasi Laporan</h3>
            <form method="POST" action="{{ route('staf.retribusi-upf.laporan') }}">
                @csrf
                <div class="field-row">
                    <div class="field">
                        <label>Dari Tanggal</label>
                        <input type="date" name="period_start" value="{{ old('period_start', now()->subMonth()->toDateString()) }}" required>
                    </div>
                    <div class="field">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="period_end" value="{{ old('period_end', now()->toDateString()) }}" required>
                    </div>
                    <div class="field">
                        <label>Cabang</label>
                        <select name="branch_id">
                            <option value="">Semua Cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label style="display:block; font-size:12px; font-weight:600; color:var(--muted); margin-bottom:8px;">Pilih Kolom</label>
                <div class="checkbox-grid">
                    @foreach ($staticColumns as $key => $label)
                        <label><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>
                    @endforeach
                    @foreach ($activeTypes as $type)
                        <label><input type="checkbox" name="columns[]" value="retribusi_line_{{ $type->id }}" checked> {{ $type->name }}</label>
                    @endforeach
                </div>

                <button type="submit" class="btn-primary">Generate Laporan</button>
            </form>
        </div>

        @if ($reportRows !== null)
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach ($reportColumns as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reportRows as $row)
                        <tr>
                            @foreach ($reportColumns as $key => $label)
                                <td>{{ $row[$key] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ max(1, count($reportColumns)) }}">Tidak ada data untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <script>
        (function () {
            // --- Tab switching ---
            var tabButtons = document.querySelectorAll('.tab-btn');
            var tabPanels = document.querySelectorAll('[data-tab-panel]');
            function activateTab(name) {
                tabButtons.forEach(function (btn) { btn.classList.toggle('active', btn.dataset.tab === name); });
                tabPanels.forEach(function (panel) { panel.classList.toggle('active', panel.dataset.tabPanel === name); });
            }
            tabButtons.forEach(function (btn) {
                btn.addEventListener('click', function () { activateTab(btn.dataset.tab); });
            });
            activateTab({{ Js::from($tab) }});

            // --- Payer type toggle ---
            var payerUmum = document.getElementById('payer-umum');
            var payerAnggota = document.getElementById('payer-anggota');
            var fieldPayerName = document.getElementById('field-payer-name');
            var fieldMember = document.getElementById('field-member');
            var fieldRetType = document.getElementById('field-retribution-type');
            var retTypeSelect = document.getElementById('retribution-type-select');
            var memberSelect = document.getElementById('member-select');

            function syncPayerFields() {
                var isAnggota = payerAnggota.checked;
                fieldPayerName.style.display = isAnggota ? 'none' : '';
                fieldMember.style.display = isAnggota ? '' : 'none';
                fieldRetType.style.display = isAnggota ? 'none' : '';
                // Clear values on the hidden side so validators don't see stale data.
                if (isAnggota) {
                    if (retTypeSelect) { retTypeSelect.value = ''; }
                } else {
                    if (memberSelect) { memberSelect.value = ''; }
                }
                renderPreview();
            }
            payerUmum.addEventListener('change', syncPayerFields);
            payerAnggota.addEventListener('change', syncPayerFields);

            // --- Live journal preview ---
            // PENTING: algoritma di bawah ini adalah PROYEKSI saja untuk tampilan.
            // Perhitungan otoritatif SELALU dilakukan ulang di server oleh
            // RetributionService::record() (via RetributionSplitCalculator) saat
            // form disubmit — kalau persentase jenis retribusi berubah di antara
            // load halaman dan submit, jawaban server yang menang, bukan preview
            // ini. Logika largest-remainder di bawah HARUS sinkron dengan
            // app/Services/Retribution/RetributionSplitCalculator.php.
            @php
                $splitTypesForJs = $splitTypes->map(fn ($type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'percentage' => (float) $type->percentage,
                    'account_code' => $type->revenueAccount?->code,
                ]);
            @endphp
            var splitTypes = @json($splitTypesForJs);
            var cashAccountLabel = 'KAS AO RIDWAN (UPF) (1101600)';

            function splitCents(totalCents, types) {
                var rows = types.map(function (t) {
                    var basisPoints = Math.round(t.percentage * 100);
                    var exact = totalCents * basisPoints;
                    return { id: t.id, floor: Math.floor(exact / 10000), remainder: exact % 10000 };
                });
                var allocated = rows.reduce(function (sum, r) { return sum + r.floor; }, 0);
                var leftover = totalCents - allocated;
                var order = rows.map(function (_, i) { return i; });
                order.sort(function (a, b) { return rows[b].remainder - rows[a].remainder; });
                for (var i = 0; i < leftover; i++) { rows[order[i]].floor++; }
                var byId = {};
                rows.forEach(function (r) { byId[r.id] = r.floor; });
                return byId;
            }

            var amountInput = document.getElementById('total-amount-input');
            var previewLines = document.getElementById('preview-lines');
            var previewEmptyMsg = document.getElementById('preview-empty-msg');

            function formatRupiah(cents) {
                return 'Rp ' + (cents / 100).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function renderPreview() {
                var value = parseFloat(amountInput.value);
                var isAnggota = payerAnggota.checked;

                if (!value || value <= 0) {
                    previewLines.style.display = 'none';
                    previewEmptyMsg.style.display = '';
                    return;
                }

                var totalCents = Math.round(value * 100);
                var html = '';

                if (isAnggota) {
                    if (splitTypes.length === 0) {
                        previewLines.style.display = 'none';
                        previewEmptyMsg.style.display = '';
                        return;
                    }
                    var splits = splitCents(totalCents, splitTypes);
                    splitTypes.forEach(function (t) {
                        html += '<div class="preview-line"><span>Kredit — ' + t.name
                            + (t.account_code ? ' (' + t.account_code + ')' : ' (belum di-link)')
                            + '</span><span>' + formatRupiah(splits[t.id]) + '</span></div>';
                    });
                } else {
                    // Umum: satu jenis retribusi (100%), tanpa pembagian.
                    var selectedOption = retTypeSelect && retTypeSelect.selectedIndex >= 0
                        ? retTypeSelect.options[retTypeSelect.selectedIndex]
                        : null;

                    if (!selectedOption || selectedOption.value === '') {
                        previewLines.innerHTML = '<p class="preview-empty">Pilih Jenis Retribusi terlebih dahulu untuk melihat pembagian jurnal.</p>';
                        previewLines.style.display = '';
                        previewEmptyMsg.style.display = 'none';
                        return;
                    }

                    var name = selectedOption.getAttribute('data-name') || selectedOption.text;
                    var code = selectedOption.getAttribute('data-code');
                    html += '<div class="preview-line"><span>Kredit — ' + name
                        + (code ? ' (' + code + ')' : ' (belum di-link)')
                        + '</span><span>' + formatRupiah(totalCents) + '</span></div>';
                }

                html += '<div class="preview-line"><span>Debit — ' + cashAccountLabel + '</span><span>' + formatRupiah(totalCents) + '</span></div>';
                html += '<div class="preview-line total"><span>Total</span><span>' + formatRupiah(totalCents) + '</span></div>';

                previewLines.innerHTML = html;
                previewLines.style.display = '';
                previewEmptyMsg.style.display = 'none';
            }

            amountInput.addEventListener('input', renderPreview);
            if (retTypeSelect) { retTypeSelect.addEventListener('change', renderPreview); }

            syncPayerFields();
        })();

        document.querySelectorAll('[data-toggle-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('cancel-form-' + btn.dataset.toggleCancel);
                form.style.display = form.style.display === 'none' ? 'flex' : 'none';
            });
        });

        // Preset periode untuk form Rekap Pendapatan UPF (harian, 2 hari, 1
        // minggu, 1 bulan) — hanya mengisi input tanggal; user tetap klik
        // tombol "Cetak Rekap" untuk membuka PDF.
        (function () {
            var startInput = document.getElementById('rekap-start');
            var endInput = document.getElementById('rekap-end');
            if (!startInput || !endInput) return;

            function toDateInputValue(d) {
                var y = d.getFullYear();
                var m = ('0' + (d.getMonth() + 1)).slice(-2);
                var day = ('0' + d.getDate()).slice(-2);
                return y + '-' + m + '-' + day;
            }

            document.querySelectorAll('[data-preset-rekap]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    var preset = link.dataset.presetRekap;
                    var end = new Date();
                    var start = new Date();
                    if (preset === 'today') {
                        // start === end === today
                    } else if (preset === '2days') {
                        start.setDate(end.getDate() - 1);
                    } else if (preset === 'week') {
                        start.setDate(end.getDate() - 6);
                    } else if (preset === 'month') {
                        start.setDate(end.getDate() - 29);
                    }
                    startInput.value = toDateInputValue(start);
                    endInput.value = toDateInputValue(end);
                });
            });
        })();
    </script>
@endsection
