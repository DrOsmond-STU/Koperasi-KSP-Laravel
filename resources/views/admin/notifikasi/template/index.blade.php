@extends('layouts.app')

@section('title', 'Template Notifikasi')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Template Notifikasi</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <table class="data-table">
        <thead><tr><th>Jenis Trigger</th><th>Channel</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($templates as $template)
                <tr>
                    <td>{{ $template->trigger_type }}</td>
                    <td>{{ ucfirst($template->channel) }}</td>
                    <td>{{ $template->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                    <td><a href="{{ route('admin.notifikasi.template.edit', $template) }}">Ubah</a></td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada template notifikasi.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
