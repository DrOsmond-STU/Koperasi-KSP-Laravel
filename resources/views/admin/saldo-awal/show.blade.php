@extends('layouts.app')

@section('title', 'Detail Batch Migrasi')

@section('content')
    <style>
        .banner { border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; font-size: 13px; }
        .banner-ok { background: #E9F1EC; color: #11543B; border: 1px solid #11543B; }
        .banner-bad { background: #F2DED7; color: #A8472F; border: 1px solid #A8472F; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .data-table th, .data-table td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--line); }
        .import-form { display: flex; gap: 10px; align-items: center; margin-top: 10px; }
        .btn-primary { padding: 8px 14px; background: var(--pine); color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .btn-lock { padding: 12px 22px; background: var(--gold); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; font-size: 14px; }
        .error-list { background: #F2DED7; border-radius: 8px; padding: 10px 14px; margin-top: 10px; font-size: 12px; color: #A8472F; }
        .locked-badge { display: inline-block; background: #E9F1EC; color: #11543B; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 12px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Batch Migrasi — {{ $batch->branch->name }} (Cut-off {{ $batch->cutoff_date->translatedFormat('d M Y') }})</h2>

    @if ($batch->status === 'locked')
        <p><span class="locked-badge">🔒 Dikunci oleh {{ $batch->lockedBy->name ?? '-' }} pada {{ $batch->locked_at?->translatedFormat('d M Y H:i') }}</span></p>
    @endif

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <div class="banner banner-bad">{{ session('error') }}</div>
    @endif

    {{-- Reconciliation Banner --}}
    <div class="banner {{ $report->isFullyBalanced() ? 'banner-ok' : 'banner-bad' }}">
        @if ($report->isFullyBalanced())
            ✓ Saldo awal balance — Debit Rp {{ number_format($report->coaDebitTotal, 0, ',', '.') }} = Kredit Rp {{ number_format($report->coaCreditTotal, 0, ',', '.') }}. Siap dikunci.
        @else
            ✗ Belum balance — Debit Rp {{ number_format($report->coaDebitTotal, 0, ',', '.') }} vs Kredit Rp {{ number_format($report->coaCreditTotal, 0, ',', '.') }}.
            @if (count($report->savingsDiscrepancies) > 0) Ada {{ count($report->savingsDiscrepancies) }} selisih Simpanan vs COA. @endif
            @if (count($report->loansDiscrepancies) > 0) Ada {{ count($report->loansDiscrepancies) }} selisih Pinjaman vs COA. @endif
        @endif
    </div>

    @if (session('import_errors') && count(session('import_errors')) > 0)
        <div class="error-list">
            <strong>Baris gagal:</strong>
            <ul>
                @foreach (session('import_errors') as $err)
                    <li>Baris {{ $err['row'] }}: {{ implode('; ', $err['errors']) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @foreach ([
        'savings' => ['label' => 'Saldo Awal Simpanan', 'count' => $batch->savings->count()],
        'loans' => ['label' => 'Saldo Awal Pinjaman', 'count' => $batch->loans->count()],
        'installments' => ['label' => 'Saldo Awal Angsuran', 'count' => $batch->loans->sum(fn($l) => $l->installments->count())],
        'coa' => ['label' => 'Saldo Awal Buku Besar (COA)', 'count' => $batch->coaLines->count()],
    ] as $key => $meta)
        <div class="panel">
            <h3>{{ $meta['label'] }} ({{ $meta['count'] }} baris)</h3>

            @if ($batch->status === 'draft')
                <form class="import-form" method="POST" action="{{ route('admin.saldo-awal.import', [$batch, $key]) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="file" accept=".csv" required>
                    <select name="mode">
                        <option value="all_or_nothing">All-or-nothing</option>
                        <option value="partial">Partial</option>
                    </select>
                    <button type="submit" class="btn-primary">Import CSV</button>
                </form>
            @endif
        </div>
    @endforeach

    @if ($batch->status === 'draft')
        <form method="POST" action="{{ route('admin.saldo-awal.lock', $batch) }}" onsubmit="return confirm('Kunci migrasi ini? Tindakan tidak bisa dibatalkan.');">
            @csrf
            <button type="submit" class="btn-lock" {{ $report->isFullyBalanced() ? '' : 'disabled' }}>Kunci Migrasi & Buat Jurnal Pembukaan</button>
        </form>
    @endif
@endsection
