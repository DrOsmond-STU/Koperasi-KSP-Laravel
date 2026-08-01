@extends('layouts.app')

@section('title', 'Manajemen Pengguna')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge.active { background: var(--leaf); color: var(--pine-bright); }
        .badge.inactive { background: var(--brick-soft); color: var(--brick); }
        .link-edit { color: var(--pine-bright); font-weight: 600; text-decoration: none; font-size: 13px; margin-right: 10px; }
        .btn-toggle { background: transparent; border: none; color: var(--brick); font-weight: 600; font-size: 13px; cursor: pointer; padding: 0; text-decoration: underline; }
        .btn-toggle.reactivate { color: var(--ok); }
    </style>

    <h2>Manajemen Pengguna</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.users.create') }}" class="btn-primary">+ Tambah Pengguna</a>
    </p>

    <table class="data-table">
        <thead>
            <tr><th>Nama</th><th>Email</th><th>Role</th><th>Cabang Scope</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                    <td>
                        @if ($user->branchScope)
                            {{ ucfirst($user->branchScope->scope_type) }}
                            @if ($user->branchScope->scope_type === 'single')
                                ({{ $user->branchScope->singleBranch?->name }})
                            @elseif ($user->branchScope->scope_type === 'multiple')
                                ({{ $user->branchScope->branches->pluck('name')->implode(', ') }})
                            @endif
                        @else
                            Belum diatur
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $user->is_active ? 'active' : 'inactive' }}">
                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('admin.users.edit', $user) }}" class="link-edit">Ubah</a>
                        @if ($user->id !== auth()->id())
                            @if ($user->is_active)
                                <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" style="display:inline;" onsubmit="return confirm('Nonaktifkan pengguna {{ $user->name }}?');">
                                    @csrf
                                    <button type="submit" class="btn-toggle">Nonaktifkan</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.users.reactivate', $user) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-toggle reactivate">Aktifkan</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada pengguna.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
