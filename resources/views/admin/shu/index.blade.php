@extends('layouts.app')

@section('title', 'Simulasi SHU')

@section('content')
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        .badge-ok { color: var(--ok); font-weight: 700; }
        .badge-bad { color: var(--brick); font-weight: 700; }
        .field-row { display: flex; gap: 10px; align-items: end; }
        .field-row label { display: block; font-size: 11px; color: var(--muted); margin-bottom: 4px; }
        .field-row input, .field-row select { padding: 7px 10px; border: 1px solid var(--line); border-radius: 8px; }
        .btn-primary { padding: 8px 14px; background: var(--pine); color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Simulasi SHU {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <form class="filter-bar" method="GET">
        <select name="branch_id" onchange="this.form.submit()">
            @if (is_null($selectedBranchId) || $branches->count() > 1)
                <option value="" {{ $isConsolidated ? 'selected' : '' }}>{{ $branches->count() > 1 ? 'Semua Cabang (Konsolidasi)' : '' }}</option>
            @endif
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" {{ $selectedBranchId === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
        </select>
        <input type="number" name="year" value="{{ $year }}" onchange="this.form.submit()">
    </form>

    <div class="panel">
        <h3>Total SHU Tahun {{ $simulation['year'] }}: Rp {{ number_format((float) $simulation['total_shu'], 0, ',', '.') }}</h3>
        <p class="{{ $simulation['is_fully_allocated'] ? 'badge-ok' : 'badge-bad' }}">
            Total Alokasi: {{ $simulation['total_percentage'] }}% — {{ $simulation['is_fully_allocated'] ? 'Teralokasi Penuh' : 'BELUM 100%' }}
        </p>

        <table class="data-table">
            <thead><tr><th>Kategori</th><th>Persentase</th><th>Nominal</th></tr></thead>
            <tbody>
                @foreach ($simulation['allocations'] as $allocation)
                    <tr>
                        <td>{{ $allocation['category']->name }}</td>
                        <td>{{ $allocation['category']->percentage }}%</td>
                        <td>Rp {{ number_format((float) $allocation['amount'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($simulation['member_shares']->isNotEmpty())
        <div class="panel">
            <h3>Rincian Jasa Anggota</h3>
            <table class="data-table">
                <thead><tr><th>Anggota</th><th>Saldo Simpanan</th><th>Bagian SHU</th></tr></thead>
                <tbody>
                    @foreach ($simulation['member_shares'] as $share)
                        <tr>
                            <td>{{ $share['member']->name }}</td>
                            <td>Rp {{ number_format((float) $share['savings_balance'], 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $share['share'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="panel">
        <h3>Kategori Alokasi SHU</h3>
        <table class="data-table">
            <thead><tr><th>Nama</th><th>Persentase</th><th>Jasa Anggota?</th></tr></thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->percentage }}%</td>
                        <td>{{ $category->is_jasa_anggota ? 'Ya' : 'Tidak' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('admin.shu.kategori.store') }}" style="margin-top: 14px;">
            @csrf
            <div class="field-row">
                <div><label>Nama Kategori</label><input type="text" name="name" required></div>
                <div><label>Persentase</label><input type="number" step="0.01" name="percentage" required></div>
                <div><label><input type="checkbox" name="is_jasa_anggota" value="1" style="width:auto;"> Jasa Anggota</label></div>
                <button type="submit" class="btn-primary">Tambah Kategori</button>
            </div>
        </form>
    </div>

    <p><a href="{{ route('admin.rat.paket.download', ['year' => $year, 'branch_id' => $selectedBranchId]) }}">Unduh Paket Laporan RAT (PDF)</a></p>
@endsection
