@extends('layouts.app')

@section('title', 'Template Kartu Anggota')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; margin-right: 10px; }
        .btn-link-muted { color: var(--muted); background: none; border: none; font-weight: 600; font-size: 13px; cursor: pointer; padding: 0; font-family: inherit; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .pill-aktif { background: var(--leaf); color: var(--pine); }
        .pill-other { background: var(--paper); color: var(--muted); }
    </style>

    <h2>Template Kartu Anggota</h2>
    <p style="color: var(--muted); margin-top: -8px;">Atur ukuran, latar, dan susunan field yang tercetak di kartu anggota. Hanya satu template yang aktif sekaligus.</p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="toolbar">
        <span></span>
        <a href="{{ route('admin.master.member-card-templates.create') }}" class="btn-primary">+ Tambah Template</a>
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Nama</th><th>Ukuran</th><th>Jumlah Field</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($templates as $template)
                <tr>
                    <td>{{ $template->name }}</td>
                    <td>{{ $template->width_mm }} × {{ $template->height_mm }} mm</td>
                    <td>{{ $template->fields_count }}</td>
                    <td><span class="pill {{ $template->is_active ? 'pill-aktif' : 'pill-other' }}">{{ $template->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <a href="{{ route('admin.master.member-card-templates.edit', $template) }}" class="btn-link">Ubah</a>
                        @unless ($template->is_active)
                            <form method="POST" action="{{ route('admin.master.member-card-templates.activate', $template) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn-link-muted">Jadikan Aktif</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada template — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
