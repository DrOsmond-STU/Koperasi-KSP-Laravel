@extends('layouts.app')

@section('title', 'Daftar Aktiva Tetap')

@section('content')
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar select { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
    </style>

    <h2>Daftar Aktiva Tetap {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>

    <form class="filter-bar" method="GET">
        <select name="branch_id" onchange="this.form.submit()">
            @if (is_null($selectedBranchId) || $branches->count() > 1)
                <option value="" {{ $isConsolidated ? 'selected' : '' }}>{{ $branches->count() > 1 ? 'Semua Cabang (Konsolidasi)' : '' }}</option>
            @endif
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" {{ $selectedBranchId === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
        </select>
    </form>

    <table class="data-table">
        <thead>
            <tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Nilai Perolehan</th><th>Akumulasi Penyusutan</th><th>Nilai Buku</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['asset']->code }}</td>
                    <td>{{ $row['asset']->name }}</td>
                    <td>{{ $row['asset']->category->name }}</td>
                    <td>Rp {{ number_format((float) $row['asset']->acquisition_cost, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((float) $row['accumulated_depreciation'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((float) $row['book_value'], 0, ',', '.') }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $row['asset']->status)) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Belum ada aktiva tetap.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
