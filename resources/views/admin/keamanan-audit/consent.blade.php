@extends('layouts.app')

@section('title', 'Log Consent')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); font-size: 12px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
    </style>

    <h2>Log Consent Per Channel</h2>

    <table class="data-table">
        <thead><tr><th>Anggota</th><th>Channel</th><th>Status</th><th>Diberikan</th><th>Ditarik</th></tr></thead>
        <tbody>
            @forelse ($consents as $consent)
                <tr>
                    <td>{{ $consent->member->name }}</td>
                    <td>{{ ucfirst($consent->channel) }}</td>
                    <td>{{ $consent->consented ? 'Aktif' : 'Ditarik' }}</td>
                    <td>{{ $consent->consented_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                    <td>{{ $consent->withdrawn_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada data consent.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
