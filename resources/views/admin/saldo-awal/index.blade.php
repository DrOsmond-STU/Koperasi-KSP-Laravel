@extends('layouts.app')

@section('title', 'Modul Saldo Awal')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .chip { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .chip-draft { background: #F3E6CC; color: #9C6A1E; }
        .chip-locked { background: #E9F1EC; color: #11543B; }
    </style>

    <h2>Modul Saldo Awal (Migrasi)</h2>

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.saldo-awal.create') }}" class="btn-primary">+ Batch Migrasi Baru</a>
    </p>

    <table class="data-table">
        <thead><tr><th>Cabang</th><th>Tanggal Cut-off</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse ($batches as $batch)
                <tr>
                    <td>{{ $batch->branch->name }}</td>
                    <td>{{ $batch->cutoff_date->translatedFormat('d M Y') }}</td>
                    <td><span class="chip {{ $batch->status === 'locked' ? 'chip-locked' : 'chip-draft' }}">{{ $batch->status === 'locked' ? 'Terkunci' : 'Draft' }}</span></td>
                    <td><a href="{{ route('admin.saldo-awal.show', $batch) }}">Buka</a></td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada batch migrasi.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
