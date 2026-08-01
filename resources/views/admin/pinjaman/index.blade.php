@extends('layouts.app')

@section('title', 'Antrian Persetujuan Pinjaman')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { padding: 6px 12px; background: var(--pine); color: #fff; border: none; border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .btn-danger { padding: 6px 12px; background: transparent; color: var(--brick); border: 1px solid var(--brick); border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Antrian Persetujuan Pinjaman</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p style="color:var(--brick); font-size:13px; margin-bottom:14px;">{{ session('error') }}</p>
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
                        <a href="{{ route('admin.print.loan-application.show', $loan) }}" class="btn-link" target="_blank">Cetak</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Tidak ada pengajuan menunggu persetujuan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Pinjaman Dicairkan Terbaru</h3>
    <table class="data-table">
        <thead><tr><th>No. Pinjaman</th><th>Anggota</th><th>Plafon</th><th>Tanggal Cair</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($disbursedLoans as $loan)
                <tr>
                    <td>{{ $loan->loan_number }}</td>
                    <td>{{ $loan->member->name }}</td>
                    <td>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                    <td>{{ $loan->disbursed_at?->translatedFormat('d M Y') }}</td>
                    <td>
                        @if ($loan->isCancelled())
                            <span style="color:var(--brick);">Dibatalkan</span>
                        @else
                            Dicairkan
                        @endif
                    </td>
                    <td>
                        @if (! $loan->isCancelled() && $loan->canBeCancelledBy(auth()->user()))
                            <button type="button" class="btn-danger" data-toggle-cancel="loan-{{ $loan->id }}">Batalkan</button>
                            <form method="POST" action="{{ route('admin.pinjaman.cancel', $loan) }}" class="cancel-form" id="cancel-form-loan-{{ $loan->id }}" style="display:none; gap:6px; margin-top:6px;">
                                @csrf
                                <input type="text" name="reason" placeholder="Alasan" required style="width:110px; padding:5px 8px; border:1px solid var(--line); border-radius:6px; font-size:11px;">
                                <button type="submit" class="btn-danger">OK</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.print.loan-application.show', $loan) }}" class="btn-link" target="_blank">Cetak Pengajuan</a>
                        <a href="{{ route('admin.print.loans.schedule', $loan) }}" class="btn-link" target="_blank">Cetak Angsuran</a>
                        @can('pinjaman.create')
                            @unless ($loan->isCancelled())
                                <a href="{{ route('staf.angsuran.create', ['loan_id' => $loan->id]) }}" class="btn-link">Catat Angsuran</a>
                            @endunless
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada pinjaman dicairkan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>
        document.querySelectorAll('[data-toggle-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('cancel-form-' + btn.dataset.toggleCancel);
                form.style.display = form.style.display === 'none' ? 'flex' : 'none';
            });
        });
    </script>
@endsection
