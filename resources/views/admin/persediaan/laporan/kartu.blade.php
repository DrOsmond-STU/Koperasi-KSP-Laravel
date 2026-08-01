@extends('layouts.app')

@section('title', 'Kartu Persediaan')

@section('content')
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
        .kpi-card .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); font-weight: 700; }
        .kpi-card .val { font-size: 19px; font-weight: 800; margin-top: 6px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        @media (max-width: 900px) { .kpi-grid { grid-template-columns: 1fr 1fr; } }
    </style>

    <h2>Kartu Persediaan {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>

    <form class="filter-bar" method="GET">
        <select name="branch_id" onchange="this.form.submit()">
            @if (is_null($selectedBranchId) || $branches->count() > 1)
                <option value="" {{ $isConsolidated ? 'selected' : '' }}>{{ $branches->count() > 1 ? 'Semua Cabang (Konsolidasi)' : '' }}</option>
            @endif
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" {{ $selectedBranchId === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
        </select>
        <select name="product_id" onchange="this.form.submit()" class="js-searchable">
            <option value="">— Pilih Barang —</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" {{ $selectedProductId === $product->id ? 'selected' : '' }}>{{ $product->code }} — {{ $product->name }}</option>
            @endforeach
        </select>
        <input type="date" name="period_start" value="{{ $periodStart }}" onchange="this.form.submit()">
        <input type="date" name="period_end" value="{{ $periodEnd }}" onchange="this.form.submit()">
    </form>

    @if ($ringkasan)
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="label">Stok Awal</div>
                <div class="val">{{ rtrim(rtrim($ringkasan['opening_qty'], '0'), '.') }}</div>
            </div>
            <div class="kpi-card">
                <div class="label">Total Masuk</div>
                <div class="val">{{ rtrim(rtrim($ringkasan['total_in'], '0'), '.') }}</div>
            </div>
            <div class="kpi-card">
                <div class="label">Total Keluar</div>
                <div class="val">{{ rtrim(rtrim($ringkasan['total_out'], '0'), '.') }}</div>
            </div>
            <div class="kpi-card">
                <div class="label">Stok Akhir</div>
                <div class="val">{{ rtrim(rtrim($ringkasan['closing_qty'], '0'), '.') }}</div>
            </div>
        </div>

        <h3>Rincian Transaksi</h3>
        <table class="data-table">
            <thead><tr><th>Tanggal</th><th>Jenis</th><th>Masuk</th><th>Keluar</th><th>Harga</th><th>Saldo Berjalan</th></tr></thead>
            <tbody>
                @forelse ($rincian as $row)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($row->transaction_date)->translatedFormat('d M Y') }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $row->transaction_type)) }}</td>
                        <td>{{ (float) $row->qty_in > 0 ? rtrim(rtrim((string) $row->qty_in, '0'), '.') : '—' }}</td>
                        <td>{{ (float) $row->qty_out > 0 ? rtrim(rtrim((string) $row->qty_out, '0'), '.') : '—' }}</td>
                        <td>Rp {{ number_format((float) $row->unit_cost, 0, ',', '.') }}</td>
                        <td>{{ rtrim(rtrim((string) $row->running_qty, '0'), '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Tidak ada mutasi pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    @else
        <p>Pilih barang untuk melihat Kartu Persediaan.</p>
    @endif
@endsection
