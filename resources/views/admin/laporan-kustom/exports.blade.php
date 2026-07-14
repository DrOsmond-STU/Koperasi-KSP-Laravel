@extends('layouts.app')

@section('title', 'Ekspor Laporan')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Ekspor Laporan Saya</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <table class="data-table">
        <thead><tr><th>Diminta</th><th>Jenis Laporan</th><th>Format</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($exports as $export)
                <tr>
                    <td>{{ $export->created_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $export->report_type }}</td>
                    <td>{{ strtoupper($export->format) }}</td>
                    <td>{{ ucfirst($export->status) }}</td>
                    <td>
                        @if ($export->isReady())
                            <a href="{{ route('admin.laporan-kustom.download', $export) }}">Unduh</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada ekspor laporan.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
