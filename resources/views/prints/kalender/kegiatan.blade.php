@extends('prints.layout')

@section('title', 'Daftar Kegiatan Koperasi')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px;">Daftar Kegiatan Koperasi</h2>
    <p style="font-size:8pt; color:#5C6E64; margin:0 0 14px;">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }}</p>

    <table class="data-table">
        <thead>
            <tr><th>Judul</th><th>Mulai</th><th>Selesai</th><th>Lokasi</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($events as $event)
                <tr>
                    <td>{{ $event->title }}</td>
                    <td>{{ $event->start_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $event->end_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $event->location ?? '—' }}</td>
                    <td>{{ ucfirst($event->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada kegiatan.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
