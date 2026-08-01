@extends('layouts.app')

@section('title', 'Jenis Retribusi')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .pill-aktif { background: var(--leaf); color: var(--pine); }
        .pill-other { background: var(--paper); color: var(--muted); }
        .pill-total { background: var(--leaf); color: var(--pine); }
        .pill-total-warn { background: #FBE7E1; color: var(--brick); }
        .pill-linked { color: var(--muted); }
        .pill-unlinked { color: var(--brick); font-style: italic; }
    </style>

    <h2>Jenis Retribusi</h2>
    <p style="color: var(--muted); margin-top: -8px;">Kelola kategori retribusi UPF beserta persentase pembagian & akun jurnalnya.</p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="toolbar">
        <span class="pill {{ bccomp((string) $activePercentageTotal, '100.00', 2) === 0 ? 'pill-total' : 'pill-total-warn' }}">
            Total Aktif: {{ number_format($activePercentageTotal, 2) }}%
            {{ bccomp((string) $activePercentageTotal, '100.00', 2) === 0 ? '' : '— BELUM 100%' }}
        </span>
        <a href="{{ route('admin.master.retribution-types.create') }}" class="btn-primary">+ Tambah Jenis</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Urutan</th><th>Kode</th><th>Nama Retribusi</th><th>Persentase</th><th>Akun COA</th><th>Status</th><th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($types as $type)
                <tr>
                    <td>{{ $type->sort_order }}</td>
                    <td>{{ $type->code }}</td>
                    <td>{{ $type->name }}</td>
                    <td>{{ number_format($type->percentage, 2) }}%</td>
                    <td class="{{ $type->coa_revenue_account_id ? 'pill-linked' : 'pill-unlinked' }}">
                        {{ $type->coa_revenue_account_id ? $type->revenueAccount->code.' — '.$type->revenueAccount->name : 'Belum di-link' }}
                    </td>
                    <td><span class="pill {{ $type->is_active ? 'pill-aktif' : 'pill-other' }}">{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td><a href="{{ route('admin.master.retribution-types.edit', $type) }}" class="btn-link">Ubah</a></td>
                </tr>
            @empty
                <tr><td colspan="7">Belum ada jenis retribusi — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
