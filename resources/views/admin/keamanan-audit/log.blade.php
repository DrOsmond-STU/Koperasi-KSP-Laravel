@extends('layouts.app')

@section('title', 'Jejak Audit')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); font-size: 12px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .filter-bar { display: flex; gap: 10px; margin-bottom: 18px; }
        .filter-bar input, .filter-bar select { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
    </style>

    <h2>Jejak Audit</h2>

    <form class="filter-bar" method="GET">
        <select name="action" onchange="this.form.submit()">
            <option value="">Semua Aksi</option>
            <option value="create" {{ ($filters['action'] ?? '') === 'create' ? 'selected' : '' }}>Create</option>
            <option value="update" {{ ($filters['action'] ?? '') === 'update' ? 'selected' : '' }}>Update</option>
            <option value="delete" {{ ($filters['action'] ?? '') === 'delete' ? 'selected' : '' }}>Delete</option>
        </select>
        <input type="text" name="auditable_type" value="{{ $filters['auditable_type'] ?? '' }}" placeholder="Nama Model (mis. App\Models\OpeningBalanceBatch)">
        <button type="submit">Filter</button>
    </form>

    <table class="data-table">
        <thead><tr><th>Waktu</th><th>Actor</th><th>Aksi</th><th>Model</th><th>ID</th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $log->actor?->name ?? '—' }}</td>
                    <td>{{ ucfirst($log->action) }}</td>
                    <td>{{ class_basename($log->auditable_type) }}</td>
                    <td>{{ $log->auditable_id }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada jejak audit yang cocok.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
