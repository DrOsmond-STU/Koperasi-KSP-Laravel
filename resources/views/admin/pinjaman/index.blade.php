@extends('layouts.app')

@section('title', 'Antrian Persetujuan Pinjaman')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { padding: 6px 12px; background: var(--pine); color: #fff; border: none; border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .btn-danger { padding: 6px 12px; background: transparent; color: #A8472F; border: 1px solid #A8472F; border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Antrian Persetujuan Pinjaman</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <table class="data-table">
        <thead>
            <tr><th>No. Pinjaman</th><th>Anggota</th><th>Produk</th><th>Plafon</th><th>Approval</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($pendingLoans as $loan)
                <tr>
                    <td>{{ $loan->loan_number }}</td>
                    <td>{{ $loan->member->name }}</td>
                    <td>{{ $loan->loanProduct->name }}</td>
                    <td>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                    <td>{{ $loan->approvalCount() }}/{{ $loan->required_approval_count }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.pinjaman.decide', $loan) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="decision" value="setuju">
                            <button type="submit" class="btn-primary">Setujui</button>
                        </form>
                        <form method="POST" action="{{ route('admin.pinjaman.decide', $loan) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="decision" value="tolak">
                            <input type="hidden" name="notes" value="Ditolak oleh pengurus">
                            <button type="submit" class="btn-danger">Tolak</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Tidak ada pengajuan menunggu persetujuan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Pinjaman Dicairkan Terbaru</h3>
    <table class="data-table">
        <thead><tr><th>No. Pinjaman</th><th>Anggota</th><th>Plafon</th><th>Tanggal Cair</th></tr></thead>
        <tbody>
            @forelse ($disbursedLoans as $loan)
                <tr>
                    <td>{{ $loan->loan_number }}</td>
                    <td>{{ $loan->member->name }}</td>
                    <td>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                    <td>{{ $loan->disbursed_at?->translatedFormat('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada pinjaman dicairkan.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
