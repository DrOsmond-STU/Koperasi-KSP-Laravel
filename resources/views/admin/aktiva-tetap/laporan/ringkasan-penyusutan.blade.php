@extends('layouts.app')

@section('title', 'Ringkasan Penyusutan per Periode')

@section('content')
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
    </style>

    <h2>Ringkasan Penyusutan per Periode {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>

    <form class="filter-bar" method="GET">
        <select name="branch_id" onchange="this.form.submit()">
            @if (is_null($selectedBranchId) || $branches->count() > 1)
                <option value="" {{ $isConsolidated ? 'selected' : '' }}>{{ $branches->count() > 1 ? 'Semua Cabang (Konsolidasi)' : '' }}</option>
            @endif
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" {{ $selectedBranchId === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
        </select>
        <input type="month" name="period" value="{{ $period }}" onchange="this.form.submit()">
    </form>

    <table class="data-table">
        <thead><tr><th>Kategori</th><th>Total Penyusutan</th></tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['category_name'] }}</td>
                    <td>Rp {{ number_format((float) $row['total_depreciation'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="2">Tidak ada penyusutan pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
