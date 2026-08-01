@extends('layouts.app')

@section('title', 'Kegiatan Koperasi')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-secondary { display: inline-block; padding: 9px 16px; background: transparent; color: var(--pine-bright); border: 1px solid var(--pine); border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-danger { padding: 6px 12px; background: transparent; color: var(--brick); border: 1px solid var(--brick); border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .header-actions { display: flex; gap: 8px; margin-bottom: 14px; }
    </style>

    <h2>Kegiatan Koperasi</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="header-actions">
        <a href="{{ route('admin.dashboard.kalender') }}" class="btn-secondary">← Lihat Kalender</a>
        <a href="{{ route('admin.kalender.kegiatan.create') }}" class="btn-primary">+ Kegiatan Baru</a>
        <a href="{{ route('admin.kalender.kegiatan.export-pdf') }}" target="_blank" class="btn-secondary">Export PDF</a>
        <a href="{{ route('admin.kalender.kegiatan.export-excel') }}" class="btn-secondary">Export Excel</a>
    </div>

    <table class="data-table">
        <thead><tr><th>Judul</th><th>Mulai</th><th>Selesai</th><th>Lokasi</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($events as $event)
                <tr>
                    <td>{{ $event->title }}</td>
                    <td>{{ $event->start_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $event->end_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $event->location ?? '—' }}</td>
                    <td>{{ ucfirst($event->status) }}</td>
                    <td>
                        @if (! in_array($event->status, ['dibatalkan', 'selesai']))
                            <form method="POST" action="{{ route('admin.kalender.kegiatan.cancel', $event) }}">
                                @csrf
                                <button type="submit" class="btn-danger">Batalkan</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada kegiatan — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
