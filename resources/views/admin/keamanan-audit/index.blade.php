@extends('layouts.app')

@section('title', 'Keamanan & Audit — RBAC & Cabang Scope')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        .nav-links a { margin-right: 14px; }
    </style>

    <h2>Keamanan & Audit</h2>
    <p class="nav-links">
        <a href="{{ route('admin.keamanan-audit.index') }}">RBAC & Cabang Scope</a>
        <a href="{{ route('admin.keamanan-audit.log') }}">Jejak Audit</a>
        <a href="{{ route('admin.keamanan-audit.consent') }}">Log Consent</a>
        @can('keamanan_audit.export')
            <a href="{{ route('admin.keamanan-audit.export') }}">Ekspor Audit (Pengawas)</a>
        @endcan
    </p>

    <div class="panel">
        <h3>Role & Permission</h3>
        <table class="data-table">
            <thead><tr><th>Role</th><th>Jumlah Permission</th></tr></thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td>{{ $role->name }}</td>
                        <td>{{ $role->permissions->count() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h3>User & Cabang Scope</h3>
        <table class="data-table">
            <thead><tr><th>Nama</th><th>Role</th><th>Cabang Scope</th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
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
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
