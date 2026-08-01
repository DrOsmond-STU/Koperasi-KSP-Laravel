@extends('layouts.app')

@section('title', $label)

@section('content')
    <style>
        .laporan-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .laporan-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-secondary { display: inline-block; padding: 9px 16px; background: transparent; color: var(--pine-bright); border: 1px solid var(--pine); border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
    </style>

    <div class="laporan-header">
        <div>
            <p style="margin: 0 0 4px; font-size: 12px;"><a href="{{ route('admin.laporan.index') }}" style="color: var(--muted); text-decoration: none;">&larr; Menu Laporan</a></p>
            <h2 style="margin: 0;">{{ $label }}</h2>
        </div>
        <div class="laporan-actions">
            <a href="{{ route('admin.laporan.export-pdf', $module) }}" class="btn-secondary">Export PDF</a>
            <a href="{{ route('admin.laporan.export-excel', $module) }}" class="btn-primary">Export Excel</a>
        </div>
    </div>

    <div class="dt-wrap" data-dt @if ($dateColumn) data-date-column="{{ $dateColumn }}" @endif>
        <div class="dt-toolbar">
            <input type="search" class="dt-search" placeholder="Cari di semua kolom...">

            @if ($dateColumn)
                <div class="dt-period">
                    <span>Periode</span>
                    <input type="date" class="dt-date-from" aria-label="Dari tanggal">
                    <span>s/d</span>
                    <input type="date" class="dt-date-to" aria-label="Sampai tanggal">
                </div>
            @endif

            @foreach ($filterable as $filterColumn)
                <select class="dt-filter" data-filter-column="{{ $filterColumn }}">
                    <option value="">{{ $columns[$filterColumn] ?? $filterColumn }} — Semua</option>
                    @foreach ($rows->pluck($filterColumn)->filter()->unique()->sort()->values() as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            @endforeach

            <span class="dt-count"></span>
        </div>

        <div class="dt-table-scroll">
            <table class="dt-table">
                <thead>
                    <tr>
                        @foreach ($columns as $key => $columnLabel)
                            <th>{{ $columnLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columns as $key => $columnLabel)
                                <td data-column="{{ $key }}">{{ $row[$key] ?? '-' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="dt-empty" @if (! $rows->isEmpty()) hidden @endif>
            {{ $rows->isEmpty() ? 'Belum ada data untuk laporan ini.' : 'Tidak ada data yang cocok dengan pencarian/filter.' }}
        </p>
    </div>
@endsection
