@extends('layouts.app')

@section('title', 'Role & Permission')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .link-edit { color: var(--pine-bright); font-weight: 600; text-decoration: none; font-size: 13px; }
        .hint { color: var(--muted); font-size: 13px; margin-bottom: 16px; }
    </style>

    <h2>Role & Permission</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <p class="hint">Atur izin akses tiap role sesuai kebijakan internal koperasi Anda. Perubahan berlaku langsung untuk semua pengguna dengan role terkait.</p>

    <table class="data-table">
        <thead>
            <tr><th>Role</th><th>Jumlah Izin</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($roles as $role)
                <tr>
                    <td>{{ $role->name }}</td>
                    <td>{{ $role->permissions_count }}</td>
                    <td><a href="{{ route('admin.role-permission.edit', $role) }}" class="link-edit">Ubah</a></td>
                </tr>
            @empty
                <tr><td colspan="3">Belum ada role.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
