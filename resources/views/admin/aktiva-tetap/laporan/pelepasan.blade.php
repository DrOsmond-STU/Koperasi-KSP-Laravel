@extends('layouts.app')

@section('title', 'Laporan Pelepasan Aktiva Tetap')

@section('content')
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar select { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .gain { color: var(--ok); font-weight: 700; }
        .loss { color: var(--brick); font-weight: 700; }
    </style>

    <h2>Laporan Pelepasan Aktiva Tetap {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>

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
        <thead><tr><th>Tanggal</th><th>Aset</th><th>Jenis</th><th>Nilai Jual</th><th>Nilai Buku</th><th>Laba/Rugi</th></tr></thead>
        <tbody>
            @forelse ($rows as $disposal)
                <tr>
                    <td>{{ $disposal->disposal_date->translatedFormat('d M Y') }}</td>
                    <td>{{ $disposal->fixedAsset->name }}</td>
                    <td>{{ ucfirst($disposal->disposal_type) }}</td>
                    <td>Rp {{ number_format((float) $disposal->sale_amount, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((float) $disposal->book_value_at_disposal, 0, ',', '.') }}</td>
                    <td class="{{ $disposal->isGain() ? 'gain' : 'loss' }}">
                        Rp {{ number_format(abs((float) $disposal->gain_loss_amount), 0, ',', '.') }} {{ $disposal->isGain() ? '(Laba)' : '(Rugi)' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada pelepasan aktiva tetap.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
