@extends('layouts.app')

@section('title', $label)

@section('content')
    <style>
        .laporan-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .laporan-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-secondary { display: inline-block; padding: 9px 16px; background: transparent; color: var(--pine-bright); border: 1px solid var(--pine); border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
    </style>

    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <div class="laporan-header">
        <div>
            <p style="margin: 0 0 4px; font-size: 12px;"><a href="{{ route('admin.laporan.index') }}" style="color: var(--muted); text-decoration: none;">&larr; Menu Laporan</a></p>
            <h2 style="margin: 0;">{{ $label }}</h2>
        </div>
        <div class="laporan-actions">
            <a href="{{ route('admin.laporan.export-pdf', $module) }}" class="btn-secondary" data-export>Export PDF</a>
            <a href="{{ route('admin.laporan.export-excel', $module) }}" class="btn-primary" data-export>Export Excel</a>
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

    {{--
        Saringan datatable bekerja di sisi klien, jadi server tidak tahu apa yang
        sedang tampil. Keadaan filter dititipkan ke URL ekspor saat tombolnya
        ditekan, lalu diterapkan ulang di server dengan aturan yang sama —
        tanpa ini, cetakan selalu berisi seluruh data meski layarnya tersaring.
    --}}
    <script>
        (function () {
            var wrap = document.querySelector('.dt-wrap[data-dt]');
            if (!wrap) { return; }

            document.querySelectorAll('[data-export]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    var p = new URLSearchParams();

                    var cari = (wrap.querySelector('.dt-search') || {}).value || '';
                    if (cari.trim() !== '') { p.set('cari', cari.trim()); }

                    wrap.querySelectorAll('.dt-filter').forEach(function (select) {
                        if (select.value !== '') { p.set('f_' + select.dataset.filterColumn, select.value); }
                    });

                    var dari = (wrap.querySelector('.dt-date-from') || {}).value || '';
                    var sampai = (wrap.querySelector('.dt-date-to') || {}).value || '';
                    if (dari !== '') { p.set('dari', dari); }
                    if (sampai !== '') { p.set('sampai', sampai); }

                    var qs = p.toString();
                    if (qs === '') { return; }

                    event.preventDefault();
                    window.location = link.href + (link.href.indexOf('?') === -1 ? '?' : '&') + qs;
                });
            });
        })();
    </script>
@endsection
