@extends('layouts.app')

@section('title', 'Master Jenis Iuran UPF')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Master Jenis Iuran (UPF)</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.master.fee-types.create') }}" class="btn-primary">+ Tambah Jenis Iuran</a>
    </p>

    <table class="data-table">
        <thead><tr><th>Kode</th><th>Nama</th><th>Satuan</th><th>Tarif</th><th>Periode</th></tr></thead>
        <tbody>
            @forelse ($feeTypes as $feeType)
                <tr>
                    <td>{{ $feeType->code }}</td>
                    <td>{{ $feeType->name }}</td>
                    <td>{{ $feeType->unit_type }}</td>
                    <td>Rp {{ number_format($feeType->tariff, 0, ',', '.') }}</td>
                    <td>{{ ucfirst($feeType->billing_period) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada jenis iuran — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
