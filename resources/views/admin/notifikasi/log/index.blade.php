@extends('layouts.app')

@section('title', 'Log Notifikasi')

@section('content')
    <style>
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
        .kpi-card .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); font-weight: 700; }
        .kpi-card .val { font-size: 21px; font-weight: 800; margin-top: 6px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
    </style>

    <h2>Log Notifikasi</h2>

    <div class="kpi-grid">
        <div class="kpi-card"><div class="label">Terkirim</div><div class="val">{{ $stats['terkirim'] }}</div></div>
        <div class="kpi-card"><div class="label">Gagal</div><div class="val">{{ $stats['gagal'] }}</div></div>
        <div class="kpi-card"><div class="label">Tanpa Consent</div><div class="val">{{ $stats['tanpa_consent'] }}</div></div>
    </div>

    <table class="data-table">
        <thead><tr><th>Waktu</th><th>Trigger</th><th>Channel</th><th>Penerima</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $log->trigger_type }}</td>
                    <td>{{ ucfirst($log->channel) }}</td>
                    <td>{{ $log->recipient }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $log->status)) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada log notifikasi.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
