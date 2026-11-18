@extends('layouts.portal')

@section('title', 'Jadwal Angsuran')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .status-tag { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 10px; }
        .status-tag.belum_bayar { background: var(--brick-soft); color: var(--brick); }
        .status-tag.sebagian { background: var(--gold-soft); color: var(--gold-deep); }
        .status-tag.lunas { background: var(--leaf); color: var(--pine-bright); }
        .empty-note { color: var(--muted); font-size: 13px; }
        .link-back { color: var(--pine-bright); font-size: 13px; text-decoration: none; }
    </style>

    <p><a href="{{ route('portal.loans.index') }}" class="link-back">&larr; Kembali ke Pinjaman Saya</a></p>
    <h2>Jadwal Angsuran — {{ $loan->loan_number }}</h2>
    <p style="color:var(--muted); font-size:13px; margin-top:-8px;">
        {{ $loan->loanProduct->name }} — Plafon Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}, tenor {{ $loan->tenorLabel() }}
    </p>
    @if ($loan->status === 'dicairkan')
        <p style="margin-top:10px;"><a href="{{ route('portal.loans.repayment.create', $loan) }}" class="link-back" style="font-weight:700;">Bayar Angsuran Mandiri &rarr;</a></p>
    @endif
    <p style="margin-top:6px;"><a href="{{ route('portal.print.loans.schedule', $loan) }}" class="link-back">Cetak Jadwal &amp; Historis Angsuran</a></p>

    <div class="panel">
        <table class="data-table">
            <thead>
                <tr><th>Angsuran ke-</th><th>Jatuh Tempo</th><th>Pokok</th><th>Jasa</th><th>Total</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($schedules as $schedule)
                    <tr>
                        <td>{{ $schedule->installment_number }}</td>
                        <td>{{ $schedule->due_date->translatedFormat('d M Y') }}</td>
                        <td>Rp {{ number_format($schedule->principal_amount, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($schedule->interest_amount, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($schedule->total_amount, 0, ',', '.') }}</td>
                        <td><span class="status-tag {{ $schedule->status }}">{{ ucfirst(str_replace('_', ' ', $schedule->status)) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><p class="empty-note">Jadwal angsuran belum tersedia (pinjaman belum dicairkan).</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
