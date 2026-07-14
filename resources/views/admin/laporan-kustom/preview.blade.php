@extends('layouts.app')

@section('title', 'Preview Laporan')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
    </style>

    <h2>Preview Laporan</h2>

    <table class="data-table">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $labels[$column] ?? $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        <td>{{ $row[$column] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">Tidak ada data untuk filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
