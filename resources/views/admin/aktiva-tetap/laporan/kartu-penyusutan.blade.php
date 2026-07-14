@extends('layouts.app')

@section('title', 'Kartu Penyusutan per Aset')

@section('content')
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar select { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
    </style>

    <h2>Kartu Penyusutan per Aset</h2>

    <form class="filter-bar" method="GET">
        <select name="fixed_asset_id" onchange="this.form.submit()">
            <option value="">— Pilih Aset —</option>
            @foreach ($assets as $asset)
                <option value="{{ $asset->id }}" {{ $selectedAssetId === $asset->id ? 'selected' : '' }}>{{ $asset->code }} — {{ $asset->name }}</option>
            @endforeach
        </select>
    </form>

    @if ($selectedAssetId)
        <table class="data-table">
            <thead><tr><th>Periode</th><th>Nilai Buku Awal</th><th>Penyusutan</th><th>Nilai Buku Akhir</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ $entry->period }}</td>
                        <td>Rp {{ number_format((float) $entry->opening_book_value, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $entry->depreciation_amount, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $entry->closing_book_value, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada penyusutan untuk aset ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    @else
        <p>Pilih aset untuk melihat Kartu Penyusutan.</p>
    @endif
@endsection
