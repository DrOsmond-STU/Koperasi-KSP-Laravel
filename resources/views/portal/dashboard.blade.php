@extends('layouts.portal')

@section('title', 'Dashboard Saya')

@section('content')
    <style>
        .kpi-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 24px; }
        .kpi-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
        .kpi-card.tone-pine { background: linear-gradient(160deg, var(--pine), var(--pine-deep)); color: #fff; border: none; }
        .kpi-card.tone-gold { background: linear-gradient(160deg, var(--gold-deep), var(--gold)); color: #fff; border: none; }
        .kpi-card .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); font-weight: 700; }
        .kpi-card.tone-pine .label, .kpi-card.tone-gold .label { color: rgba(255,255,255,.75); }
        .kpi-card .val { font-size: 21px; font-weight: 800; margin-top: 6px; font-variant-numeric: tabular-nums; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .panel h3 { margin-top: 0; font-size: 14px; }
        .problem-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 13px; }
        .problem-row:last-child { border-bottom: none; }
        .empty-note { color: var(--muted); font-size: 13px; }
        @media (max-width: 700px) { .kpi-grid { grid-template-columns: 1fr; } }
    </style>

    <h2>Selamat datang, {{ $member->name }}</h2>
    <p style="color:var(--muted); font-size:13px; margin-top:-8px;">Nomor Anggota: {{ $member->member_number }}</p>

    @if (session('status'))
        <p style="color: var(--ok); font-size: 13px; margin-bottom: 14px;">{{ session('status') }}</p>
    @endif

    <div class="kpi-grid">
        <div class="kpi-card tone-pine">
            <div class="label">Total Saldo Simpanan</div>
            <div class="val">Rp {{ number_format($totalSavings, 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card tone-gold">
            <div class="label">Total Pinjaman Berjalan</div>
            <div class="val">Rp {{ number_format($totalLoanOutstanding, 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="panel">
        <h3>Angsuran Jatuh Tempo Terdekat</h3>
        @forelse ($dueInstallments as $schedule)
            <div class="problem-row">
                <span>{{ $schedule->loan->loan_number }} — Angsuran ke-{{ $schedule->installment_number }}</span>
                <span>
                    Rp {{ number_format($schedule->total_amount, 0, ',', '.') }}
                    <span style="color:var(--muted);">— {{ $schedule->due_date->translatedFormat('d M Y') }}</span>
                </span>
            </div>
        @empty
            <p class="empty-note">Tidak ada angsuran jatuh tempo saat ini.</p>
        @endforelse
    </div>

    <div class="panel">
        <h3>Rekening Simpanan</h3>
        @forelse ($savingsAccounts as $account)
            <div class="problem-row">
                <span>{{ $account->account_number }} — {{ $account->savingsProduct->name }}</span>
                <span>Rp {{ number_format($account->balance, 0, ',', '.') }}</span>
            </div>
        @empty
            <p class="empty-note">Belum ada rekening simpanan aktif.</p>
        @endforelse
    </div>

    <div class="panel">
        <h3>Pinjaman Berjalan</h3>
        @forelse ($activeLoans as $loan)
            <div class="problem-row">
                <span>{{ $loan->loan_number }}</span>
                <span>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</span>
            </div>
        @empty
            <p class="empty-note">Tidak ada pinjaman berjalan.</p>
        @endforelse
    </div>
@endsection
