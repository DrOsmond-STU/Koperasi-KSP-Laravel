@extends('layouts.portal')

@section('title', 'Pinjaman Saya')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .link-history { color: var(--pine-bright); font-weight: 600; text-decoration: none; font-size: 13px; }
        .status-tag { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 10px; }
        .status-tag.pending { background: var(--gold-soft); color: var(--gold-deep); }
        .status-tag.success { background: var(--leaf); color: var(--pine-bright); }
        .status-tag.danger { background: var(--brick-soft); color: var(--brick); }
        .empty-note { color: var(--muted); font-size: 13px; }
    </style>

    <h2>Pinjaman Saya</h2>

    <p><a href="{{ route('portal.print.loans') }}" class="link-history">Cetak Pinjaman Saya</a></p>

    @php
        $statusLabels = ['diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'dicairkan' => 'Dicairkan', 'lunas' => 'Lunas', 'dibatalkan' => 'Dibatalkan'];
        $statusTone = ['diajukan' => 'pending', 'disetujui' => 'pending', 'ditolak' => 'danger', 'dicairkan' => 'success', 'lunas' => 'success', 'dibatalkan' => 'danger'];
    @endphp

    <table class="data-table">
        <thead>
            <tr><th>No. Pinjaman</th><th>Produk</th><th>Plafon</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($loans as $loan)
                <tr>
                    <td>{{ $loan->loan_number }}</td>
                    <td>{{ $loan->loanProduct->name }}</td>
                    <td>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                    <td><span class="status-tag {{ $statusTone[$loan->status] ?? 'pending' }}">{{ $statusLabels[$loan->status] ?? ucfirst($loan->status) }}</span></td>
                    <td><a href="{{ route('portal.loans.schedule', $loan) }}" class="link-history">Lihat Jadwal Angsuran</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><p class="empty-note">Belum ada pinjaman.</p></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
